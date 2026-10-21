<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\OrgStructure;
use App\Models\OrgUnit;
use App\Models\User;
use App\Services\OrgService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class OrgStructureTest extends TestCase
{
    use RefreshDatabase;

    private User $hr;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-26 09:00:00');
        $this->hr = User::create(['name' => 'HR Officer', 'username' => 'hr', 'password' => 'secret123', 'role' => 'admin']);
    }

    private function person(string $no, string $last): Employee
    {
        return Employee::create(['employee_no' => $no, 'last_name' => $last, 'first_name' => 'A', 'employment_type' => 'permanent', 'office' => 'Old office']);
    }

    public function test_build_approved_structure_assign_and_sync_supervisors(): void
    {
        $ed = $this->person('1', 'Director');
        $dm = $this->person('2', 'Manager');
        $chief = $this->person('3', 'Chief');
        $staff = $this->person('4', 'Staff');
        $auditor = $this->person('5', 'Auditor');

        $this->actingAs($this->hr)->post('/admin/org', ['name' => 'Approved 2019', 'kind' => 'approved'])->assertRedirect();
        $s = OrgStructure::sole();
        $add = fn (array $d) => $this->actingAs($this->hr)->post("/admin/org/{$s->id}/units", $d);

        $add(['name' => 'Office of the Executive Director', 'code' => 'OED', 'level' => 'office', 'head_employee_id' => $ed->id, 'head_title' => 'Executive Director'])->assertSessionHasNoErrors();
        $oed = OrgUnit::where('code', 'OED')->sole();
        $add(['name' => 'Finance and Administration', 'code' => 'FAD', 'level' => 'department', 'parent_id' => $oed->id, 'head_employee_id' => $dm->id])->assertSessionHasNoErrors();
        $fad = OrgUnit::where('code', 'FAD')->sole();
        $add(['name' => 'Accounting Division', 'code' => 'ACC', 'level' => 'division', 'parent_id' => $fad->id, 'head_employee_id' => $chief->id])->assertSessionHasNoErrors();
        // A unit can sit directly under the office (skipping department and division).
        $add(['name' => 'Internal Audit Unit', 'code' => 'IAU', 'level' => 'unit', 'parent_id' => $oed->id])->assertSessionHasNoErrors();
        // But a department can't be under a division.
        $acc = OrgUnit::where('code', 'ACC')->sole();
        $add(['name' => 'Wrong', 'code' => 'X', 'level' => 'department', 'parent_id' => $acc->id])->assertSessionHasErrors('parent_id');

        $this->actingAs($this->hr)->post("/admin/org/{$s->id}/units/{$acc->id}/assign", ['employee_ids' => [$staff->id]])->assertSessionHasNoErrors();
        $this->actingAs($this->hr)->post("/admin/org/{$s->id}/units/".OrgUnit::where('code', 'IAU')->value('id').'/assign', ['employee_ids' => [$auditor->id]]);

        // Not active yet → office text unchanged; activating syncs it.
        $this->assertSame('Old office', $staff->fresh()->office);
        $this->actingAs($this->hr)->put("/admin/org/{$s->id}", ['action' => 'activate']);
        $this->assertTrue($s->fresh()->isCurrent());
        $this->assertSame('Accounting Division', $staff->fresh()->office);
        $this->assertSame(['Office of the Executive Director', 'Finance and Administration', 'Accounting Division'], collect($staff->currentUnit()->path())->pluck('name')->all());

        $this->actingAs($this->hr)->post("/admin/org/{$s->id}/sync-supervisors");
        $this->assertSame($chief->id, $staff->fresh()->supervisor_id);   // unit head
        $this->assertSame($dm->id, $chief->fresh()->supervisor_id);      // head → next unit up
        $this->assertSame($ed->id, $dm->fresh()->supervisor_id);
        $this->assertSame($ed->id, $auditor->fresh()->supervisor_id);    // unit directly under the office (no unit head)

        $this->actingAs($this->hr)->get("/admin/org/{$s->id}?unit={$acc->id}")->assertOk()->assertSee('Accounting Division');
        $this->actingAs($this->hr)->get('/admin/org')->assertOk()->assertSee('In effect today: Approved 2019');
        $this->actingAs($this->hr)->get("/print/org/{$s->id}")->assertOk()->assertSee('ORGANIZATIONAL CHART')->assertSee('Internal Audit Unit');
        $this->actingAs($this->hr)->get("/admin/employees/{$staff->id}")->assertOk()->assertSee('Finance and Administration › Accounting Division');
    }

    public function test_interim_structure_overrides_approved_while_in_effect(): void
    {
        $staff = $this->person('4', 'Staff');
        $approved = OrgStructure::create(['name' => 'Approved', 'kind' => 'approved', 'status' => 'active', 'effective_from' => '2019-01-01']);
        $acc = $approved->units()->create(['name' => 'Accounting Division', 'code' => 'ACC', 'level' => 'division']);
        app(OrgService::class)->assign($acc, [$staff->id]);

        // Interim copy, then move the person to a new unit there.
        $this->actingAs($this->hr)->post('/admin/org', ['name' => 'Interim Q4', 'kind' => 'interim', 'effective_from' => '2026-10-01', 'effective_to' => '2026-12-31', 'copy_from' => $approved->id]);
        $interim = OrgStructure::where('name', 'Interim Q4')->sole();
        $this->assertSame(1, $interim->units()->count());
        $this->assertSame(1, $interim->assignments()->count());
        $task = $interim->units()->create(['name' => 'Reorganization Task Force', 'code' => 'RTF', 'level' => 'unit']);
        $this->actingAs($this->hr)->post("/admin/org/{$interim->id}/units/{$task->id}/assign", ['employee_ids' => [$staff->id]]);
        $this->assertSame('Accounting Division', $staff->fresh()->office); // interim still a draft

        $this->actingAs($this->hr)->put("/admin/org/{$interim->id}", ['action' => 'activate']);
        $this->assertSame($interim->id, OrgStructure::current()->id);
        $this->assertSame('active', $approved->fresh()->status); // approved stays active underneath
        $this->assertSame('Reorganization Task Force', $staff->fresh()->office);
        $this->assertSame($approved->id, OrgStructure::current('2027-01-15')->id); // after the interim ends
    }

    public function test_org_units_csv_import_links_parents_in_any_order(): void
    {
        $this->person('2015-001', 'Head');
        OrgStructure::create(['name' => 'Approved structure 2019', 'kind' => 'approved']);
        $csv = "structure,code,name,level,parent_code,head_employee_no\n"
            ."Approved structure 2019,PAY,Payroll Unit,unit,ACC,\n"
            ."Approved structure 2019,ACC,Accounting Division,division,FAD,2015-001\n"
            ."Approved structure 2019,FAD,Finance Department,department,,\n"
            ."Approved structure 2019,BAD,Bad,department,PAY,\n";
        $res = $this->actingAs($this->hr)->post('/admin/import/org_units', ['file' => UploadedFile::fake()->createWithContent('o.csv', $csv)]);
        $preview = $res->headers->get('Location');
        $this->actingAs($this->hr)->get($preview)->assertSee("A department can't be under a unit");
        $this->actingAs($this->hr)->post($preview);

        $pay = OrgUnit::where('code', 'PAY')->sole();
        $this->assertSame(['FAD', 'ACC', 'PAY'], collect($pay->path())->pluck('code')->all());
        $this->assertSame(1, OrgUnit::where('code', 'ACC')->sole()->assignments()->count()); // head assigned
        $this->assertSame(0, OrgUnit::where('code', 'BAD')->count());
    }
}
