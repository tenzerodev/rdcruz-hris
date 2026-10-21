<?php

namespace Tests\Feature;

use App\Imports\Registry;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveApplication;
use App\Models\Punch;
use App\Models\TravelOrder;
use App\Models\User;
use App\Services\LeaveService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use RefreshDatabase;

    private User $hr;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-26 09:00:00');
        $this->hr = User::create(['name' => 'HR Officer', 'username' => 'hr', 'password' => 'secret123', 'role' => 'admin']);
    }

    private function csv(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('data.csv', $content);
    }

    /** Upload, follow to preview, import; returns the preview HTML. */
    private function import(string $key, string $content, bool $run = true): string
    {
        $res = $this->actingAs($this->hr)->post("/admin/import/$key", ['file' => $this->csv($content)]);
        $res->assertSessionHasNoErrors()->assertRedirect();
        $preview = $res->headers->get('Location');
        $html = $this->actingAs($this->hr)->get($preview)->assertOk()->getContent();
        if ($run && str_contains($html, 'Ready to import')) {
            $this->actingAs($this->hr)->post($preview);
        }

        return $html;
    }

    public function test_every_section_has_instructions_and_a_template_that_validates(): void
    {
        $this->actingAs($this->hr)->get('/admin/import')->assertOk();
        foreach (Registry::all() as $key => $importer) {
            $this->actingAs($this->hr)->get("/admin/import/$key")->assertOk()->assertSee('Download template');
            $csv = $this->actingAs($this->hr)->get("/admin/import/$key/template")->assertOk()->streamedContent();
            $this->assertStringStartsWith("\xEF\xBB\xBF".implode(',', $importer->headers()), $csv);
            // The template's own example rows parse with every column recognized.
            $parsed = $importer->parse($csv);
            $this->assertSame([], $parsed['unknown'], $key);
            $this->assertSame([], $parsed['missing'], $key);
        }
    }

    public function test_employees_import_with_supervisors_logins_and_errors(): void
    {
        $html = $this->import('employees', <<<'CSV'
employee_no,last_name,first_name,employment_type,position,office,supervisor_employee_no,birth_date,monthly_salary,username,password,email
2015-001,Villanueva,Rosa,permanent,Division Chief,Admin,,05/14/1975,"98,000",rvillanueva,Secret2026!,rosa@agency.test
2019-014,Dela Cruz,Juan,permanent,AO V,Admin,2015-001,1990-01-02,52000,,,
COS-1,Garcia,Mika,cos,Artist,Marketing,9999,,,,,
BAD-1,Cruz,,regular,,,,,,,,not-an-email
CSV);
        $this->assertStringContainsString('Supervisor “9999” is not in this file', $html);
        $this->assertStringContainsString('First name', $html); // missing first name error mentions the column
        $this->assertSame(2, Employee::count());
        $juan = Employee::where('employee_no', '2019-014')->sole();
        $this->assertSame('2015-001', $juan->supervisor->employee_no);
        $this->assertSame('98000.00', Employee::where('employee_no', '2015-001')->value('monthly_salary'));
        $this->assertSame('1975-05-14', Employee::where('employee_no', '2015-001')->sole()->birth_date->toDateString());
        $this->assertNotNull(User::where('username', 'rvillanueva')->first());

        // Re-import updates only the filled-in columns.
        $this->import('employees', "employee_no,last_name,first_name,employment_type,office\n2019-014,Dela Cruz,Juan,permanent,Finance\n");
        $juan->refresh();
        $this->assertSame(['Finance', 'AO V'], [$juan->office, $juan->position]);
    }

    public function test_attendance_leave_travel_and_balances_imports(): void
    {
        $e = Employee::create(['employee_no' => 'E1', 'last_name' => 'Reyes', 'first_name' => 'Ana', 'employment_type' => 'permanent', 'schedule_type' => 'flexi']);

        // Semicolon-separated (some Excel locales) with 12-hour times; existing punches are kept.
        Punch::create(['employee_id' => $e->id, 'work_date' => '2026-10-05', 'type' => 'am_in', 'punched_at' => '2026-10-05 07:45', 'source' => 'kiosk']);
        $this->import('attendance', "Employee No;Date;AM In;PM Out\nE1;10/05/2026;8:00 AM;5:30 PM\nE1;2026-10-06;08:10;18:00\nE1;2026-10-07;;\n");
        $this->assertSame('07:45', Punch::where('employee_id', $e->id)->whereDate('work_date', '2026-10-05')->where('type', 'am_in')->sole()->punched_at->format('H:i'));
        $this->assertSame('17:30', Punch::where('employee_id', $e->id)->whereDate('work_date', '2026-10-05')->where('type', 'pm_out')->sole()->punched_at->format('H:i'));
        $this->assertSame(4, Punch::where('employee_id', $e->id)->count());

        $this->import('leave_history', "employee_no,type,date_from,date_to\nE1,vl,2026-10-08,2026-10-09\n");
        $this->assertSame(2.0, LeaveApplication::sole()->days);
        $this->import('leave_balances', "employee_no,balance,as_of_date,amount\nE1,VL,2025-12-31,12.5\nE1,SL,2025-12-31,20\n");
        $this->assertSame(12.5, app(LeaveService::class)->balances($e)['VL']);

        $this->import('travel', "employee_nos,type,date_from,date_to,destination,purpose\nE1,ob,2026-10-12,2026-10-12,DTI,Meeting\n");
        $this->assertSame($e->id, TravelOrder::sole()->employees->first()->id);

        $this->import('holidays', "date,name,type\n2027-01-01,New Year's Day,\n2027-02-25,EDSA,special\n");
        $this->assertSame(2, Holiday::count());
    }

    public function test_bad_files_are_rejected_and_preview_saves_nothing(): void
    {
        $this->actingAs($this->hr)->post('/admin/import/positions', ['file' => $this->csv("title,office\nAO,Admin\n")])->assertSessionHasErrors('file');
        $this->actingAs($this->hr)->post('/admin/import/positions', ['file' => $this->csv("item_no,title\n")])->assertSessionHasErrors('file');
        $this->import('positions', "item_no,title,salary_grade\nP-1,AO II,11\n", run: false);
        $this->assertDatabaseCount('positions', 0);

        $user = User::create(['name' => 'Emp', 'username' => 'emp', 'password' => 'secret123', 'role' => 'employee']);
        $this->actingAs($user)->get('/admin/import')->assertForbidden();
    }
}
