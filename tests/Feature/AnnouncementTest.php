<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Employee;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    private function person(string $username, string $office, string $type = 'permanent'): Employee
    {
        $user = User::create(['name' => ucfirst($username), 'username' => $username, 'password' => 'secret123', 'role' => 'employee']);

        return Employee::create(['employee_no' => strtoupper($username), 'last_name' => ucfirst($username), 'first_name' => 'X', 'employment_type' => $type, 'office' => $office, 'user_id' => $user->id]);
    }

    public function test_hr_posts_a_memo_to_an_office_and_tracks_acknowledgments(): void
    {
        Storage::fake('local');
        Setting::put(['session_idle_minutes' => '0', 'notifications_enabled' => '0']);
        $hr = User::create(['name' => 'HR', 'username' => 'hr', 'password' => 'secret123', 'role' => 'hr']);
        $ana = $this->person('ana', 'FAD');
        $ben = $this->person('ben', 'FAD');
        $cora = $this->person('cora', 'OED');

        $this->actingAs($hr)->post('/admin/announcements', [
            'kind' => 'memo', 'reference_no' => 'Memo 2026-045', 'title' => 'Flag ceremony every Monday', 'body' => "All **FAD** staff must attend.\n\n- 8:00 AM, lobby",
            'offices' => ['FAD'], 'requires_ack' => 1, 'publish' => 1, 'attachment' => UploadedFile::fake()->create('memo.pdf', 100, 'application/pdf'),
        ])->assertSessionHasNoErrors();
        $memo = Announcement::sole();
        $this->assertNotNull($memo->published_at);
        $this->assertSame(2, $memo->recipients()->count());
        $this->assertSame(1, $ana->user->notifications()->count());
        $this->assertSame(0, $cora->user->notifications()->count());

        // Ana sees it on her home page, reads and acknowledges it.
        $this->actingAs($ana->user)->get('/me')->assertOk()->assertSee('Flag ceremony every Monday');
        $this->get("/me/memos/{$memo->id}")->assertOk()->assertSee('<strong>FAD</strong>', false)->assertSee('I have read and understood');
        $this->get("/me/memos/{$memo->id}/attachment")->assertOk();
        $this->post("/me/memos/{$memo->id}/acknowledge")->assertSessionHas('status');
        $this->get('/me')->assertDontSee('Please read and acknowledge');

        // Cora (other office) can't see it.
        $this->actingAs($cora->user)->get("/me/memos/{$memo->id}")->assertNotFound();
        $this->get("/me/memos/{$memo->id}/attachment")->assertNotFound();

        $this->actingAs($hr)->get("/admin/announcements/{$memo->id}")->assertOk()->assertSee('Acknowledged: 1 of 2');
        $csv = $this->get("/admin/announcements/{$memo->id}?format=csv")->streamedContent();
        $this->assertStringContainsString('NOT YET', $csv);

        // Taken down: employees no longer see it.
        $this->post("/admin/announcements/{$memo->id}/unpublish");
        $this->actingAs($ben->user)->get("/me/memos/{$memo->id}")->assertNotFound();

        // Employees can't manage memos.
        $this->post('/admin/announcements', [])->assertForbidden();
    }
}
