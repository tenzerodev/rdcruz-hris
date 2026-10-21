<?php

namespace Tests\Feature;

use App\Models\DocumentVerification;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\Punch;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VerificationTest extends TestCase
{
    use RefreshDatabase;

    private User $hr;

    private Employee $e;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-26 09:00:00');
        config(['app.url' => 'https://hris.agency.gov.ph']);
        $this->hr = User::create(['name' => 'HR Officer', 'username' => 'hr', 'password' => 'secret123', 'role' => 'admin']);
        $this->e = Employee::create(['employee_no' => 'V1', 'last_name' => 'Aquino', 'first_name' => 'Lea', 'employment_type' => 'permanent', 'position' => 'Officer', 'date_hired' => '2020-01-06']);
    }

    public function test_every_printout_carries_a_verification_code(): void
    {
        $leave = LeaveApplication::create(['employee_id' => $this->e->id, 'type' => 'SL', 'date_from' => '2026-10-20', 'date_to' => '2026-10-20', 'days' => 1, 'status' => 'pending']);
        $urls = [
            '/admin/dtr/print?month=2026-10&employee_ids[]='.$this->e->id => 'form48',
            "/print/leave/{$leave->id}" => 'form6',
            "/print/service-record/{$this->e->id}" => 'service_record',
            "/print/certificate/{$this->e->id}?purpose=loan" => 'coe',
            "/print/pds/{$this->e->id}" => 'pds',
            '/admin/overtime/report?month=2026-10&employee_id='.$this->e->id => 'overtime_report',
        ];
        foreach ($urls as $url => $type) {
            $html = $this->actingAs($this->hr)->get($url)->assertOk()->assertSee('https://hris.agency.gov.ph/verify')->getContent();
            $doc = DocumentVerification::where('type', $type)->latest('id')->firstOrFail();
            $this->assertStringContainsString($doc->display_code, $html, $type);
            $this->assertStringContainsString('<svg', $html, $type);
        }
    }

    public function test_reprint_reuses_code_changes_supersede_and_revoke(): void
    {
        $url = '/admin/dtr/print?month=2026-10&employee_ids[]='.$this->e->id;
        $this->actingAs($this->hr)->get($url);
        $this->actingAs($this->hr)->get($url);
        $this->assertSame(1, DocumentVerification::count());
        $first = DocumentVerification::sole();

        // Public page: no login needed, shows the facts to compare.
        auth()->logout();
        $this->get('/verify/'.$first->display_code)->assertOk()->assertSee('Authentic document')->assertSee('October 2026')->assertSee('AQUINO, Lea');
        $this->get('/verify?code='.strtolower($first->code))->assertRedirect('/verify/'.$first->code);
        $this->get('/verify/ZZZZZZZZZZZZ')->assertNotFound()->assertSee('No document found');

        // A DTR correction changes the content → new code; the old one is outdated.
        Punch::create(['employee_id' => $this->e->id, 'work_date' => '2026-10-21', 'type' => 'am_in', 'punched_at' => '2026-10-21 08:00', 'source' => 'manual']);
        Punch::create(['employee_id' => $this->e->id, 'work_date' => '2026-10-21', 'type' => 'pm_out', 'punched_at' => '2026-10-21 17:00', 'source' => 'manual']);
        $this->actingAs($this->hr)->get($url);
        $this->assertSame(2, DocumentVerification::count());
        auth()->logout();
        $this->get('/verify/'.$first->code)->assertOk()->assertSee('Authentic, but outdated');

        $second = DocumentVerification::latest('id')->first();
        $this->actingAs($this->hr)->post("/admin/documents/{$second->id}/revoke", ['reason' => 'Issued in error'])->assertSessionHasNoErrors();
        auth()->logout();
        $this->get('/verify/'.$second->code)->assertOk()->assertSee('Not valid')->assertSee('Issued in error');
        $this->actingAs($this->hr)->get('/admin/documents')->assertOk()->assertSee($second->display_code);
    }

    public function test_logo_upload_appears_on_printouts(): void
    {
        Storage::fake('public');
        $this->actingAs($this->hr)->post('/admin/settings/logo', ['logo' => UploadedFile::fake()->image('logo.png', 200, 200)])->assertSessionHasNoErrors();
        $path = Setting::get('logo_path');
        Storage::disk('public')->assertExists($path);

        $this->actingAs($this->hr)->get("/print/service-record/{$this->e->id}")->assertSee('storage/'.$path);
        $this->actingAs($this->hr)->get('/admin')->assertSee('storage/'.$path);
        $this->actingAs($this->hr)->post('/admin/settings/logo', ['logo' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml')])->assertSessionHasErrors('logo');

        $this->actingAs($this->hr)->post('/admin/settings/logo', ['remove' => 1]);
        Storage::disk('public')->assertMissing($path);
        $this->assertSame('', Setting::get('logo_path'));
    }
}
