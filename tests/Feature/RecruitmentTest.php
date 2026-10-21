<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vacancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RecruitmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_vacancy_to_appointment(): void
    {
        Storage::fake('local');
        Setting::put(['session_idle_minutes' => '0', 'notifications_enabled' => '0']);
        $hr = User::create(['name' => 'HR', 'username' => 'hr', 'password' => 'secret123', 'role' => 'hr']);
        $position = Position::create(['item_no' => 'CITEM-AO5-1', 'title' => 'Administrative Officer V', 'salary_grade' => 18, 'office' => 'FAD', 'is_active' => true]);

        // HR posts the vacant item.
        $this->actingAs($hr)->get('/admin/recruitment')->assertOk()->assertSee('CITEM-AO5-1');
        $this->get("/admin/recruitment/new?position={$position->id}")->assertOk()->assertSee('Administrative Officer V');
        $this->post('/admin/recruitment', ['position_id' => $position->id, 'title' => 'Administrative Officer V', 'item_no' => 'CITEM-AO5-1', 'salary_grade' => 18,
            'monthly_salary' => 51357, 'employment_type' => 'permanent', 'slots' => 1, 'education' => "Bachelor's degree", 'posted_on' => today()->toDateString(),
            'closing_on' => today()->addDays(15)->toDateString(), 'public' => 1])->assertSessionHasNoErrors();
        $vacancy = Vacancy::sole();
        $this->get('/careers')->assertDontSee('Administrative Officer V'); // still a draft
        $this->post("/admin/recruitment/{$vacancy->id}/status", ['status' => 'open']);

        // An applicant applies online.
        auth()->logout();
        $this->get('/careers')->assertOk()->assertSee('Administrative Officer V');
        $form = ['last_name' => 'Reyes', 'first_name' => 'Maria', 'email' => 'maria@example.com', 'mobile' => '0917', 'education' => 'BS Accountancy, UP, 2015', 'consent' => 1,
            'files' => ['letter' => UploadedFile::fake()->create('letter.pdf', 50, 'application/pdf'), 'pds' => UploadedFile::fake()->create('pds.pdf', 80, 'application/pdf')]];
        $this->post("/careers/{$vacancy->id}", ['website' => 'spam'] + $form)->assertRedirect('/careers');
        $this->assertSame(0, Applicant::count()); // honeypot
        $this->post("/careers/{$vacancy->id}", $form)->assertRedirect('/careers/thank-you');
        $maria = Applicant::sole();
        $this->assertCount(2, $maria->documents);
        $this->post("/careers/{$vacancy->id}", $form)->assertSessionHasErrors('email'); // duplicate
        $this->post('/careers/status', ['reference_no' => $maria->reference_no, 'email' => 'MARIA@example.com'])->assertOk()->assertSee('under initial review');
        $this->post('/careers/status', ['reference_no' => $maria->reference_no, 'email' => 'other@example.com'])->assertSessionHasErrors('reference_no');
        $this->assertTrue($hr->notifications()->exists());

        // HRMPSB assessment by two members; the average decides the ranking.
        $this->actingAs($hr)->put("/admin/recruitment/applicants/{$maria->id}", ['status' => 'qualified']);
        $scores = ['education' => 18, 'training' => 8, 'experience' => 17, 'performance' => 18, 'potential' => 12, 'interview' => 13]; // 86
        $this->post("/admin/recruitment/applicants/{$maria->id}/assess", ['assessor' => 'Chair', 'scores' => $scores])->assertSessionHasNoErrors();
        $this->post("/admin/recruitment/applicants/{$maria->id}/assess", ['assessor' => 'Member', 'scores' => ['interview' => 9] + $scores]); // 82
        $this->post("/admin/recruitment/applicants/{$maria->id}/assess", ['assessor' => 'X', 'scores' => ['education' => 25] + $scores])->assertSessionHasErrors('scores.education');
        $this->assertSame(84.0, $maria->fresh()->averageScores()['total']);
        $this->get("/admin/recruitment/{$vacancy->id}/comparative")->assertOk()->assertSee('REYES, Maria')->assertSee('84.00');
        $this->get("/admin/recruitment/applicants/{$maria->id}/documents/1")->assertOk();
        $this->get('/admin/recruitment/publication')->assertOk()->assertSee('CITEM-AO5-1');

        // Selected and appointed: a new employee with a service record, and the vacancy is filled.
        $this->put("/admin/recruitment/applicants/{$maria->id}", ['status' => 'selected']);
        $this->post("/admin/recruitment/applicants/{$maria->id}/appoint", ['employee_no' => '2026-101', 'employment_type' => 'permanent',
            'date_hired' => '2026-12-01', 'step' => 1, 'monthly_salary' => 51357])->assertRedirect();
        $employee = Employee::where('employee_no', '2026-101')->sole();
        $this->assertSame([$position->id, 'Administrative Officer V', 18], [$employee->position_id, $employee->position, $employee->salary_grade]);
        $this->assertSame('Original appointment', $employee->serviceRecords()->sole()->remarks);
        $this->assertSame(['appointed', 'filled'], [$maria->fresh()->status, $vacancy->fresh()->status]);
        $this->assertSame(0, Vacancy::accepting()->count());
    }

    public function test_applicant_files_need_recruitment_access(): void
    {
        $records = User::create(['name' => 'Rec', 'username' => 'rec', 'password' => 'secret123', 'role' => 'payroll']);
        $vacancy = Vacancy::create(['title' => 'Driver', 'status' => 'open', 'employment_type' => 'cos', 'slots' => 1]);
        $a = Applicant::create(['vacancy_id' => $vacancy->id, 'last_name' => 'A', 'first_name' => 'B', 'email' => 'a@b.c', 'documents' => []]);
        $this->actingAs($records)->get("/admin/recruitment/applicants/{$a->id}")->assertForbidden();
    }
}
