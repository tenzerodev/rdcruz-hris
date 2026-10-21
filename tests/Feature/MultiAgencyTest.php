<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\Agencies;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** Several agencies on one server: separate databases chosen by web address. */
class MultiAgencyTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = storage_path('framework/testing/agencies-'.uniqid());
        File::ensureDirectoryExists($this->dir);
        config(['hris.multi_agency.enabled' => true, 'hris.multi_agency.registry' => $this->dir.'/agencies.json']);
    }

    protected function tearDown(): void
    {
        Agencies::reset();
        File::deleteDirectory($this->dir);
        foreach (['alpha', 'beta'] as $id) {
            File::deleteDirectory(storage_path('app/agencies/'.$id));
            File::deleteDirectory(storage_path('framework/sessions/'.$id));
        }
        parent::tearDown();
    }

    public function test_each_agency_has_its_own_data_and_unknown_addresses_are_refused(): void
    {
        foreach (['alpha' => 'Alpha Agency', 'beta' => 'Beta Agency'] as $id => $name) {
            $this->artisan('hris:agency:add', ['id' => $id, '--host' => ["$id.test"], '--name' => $name, '--database' => "{$this->dir}/$id.sqlite"])->assertSuccessful();
        }
        $this->assertCount(2, Agencies::all());
        $this->artisan('hris:agency:add', ['id' => 'gamma', '--host' => ['alpha.test']])->expectsOutputToContain('already used by alpha');

        // A user in Alpha's database does not exist in Beta's.
        Agencies::activate(Agencies::find('alpha'));
        User::create(['name' => 'Alpha Admin', 'username' => 'admin', 'password' => 'secret123', 'role' => 'admin']);
        $this->assertSame('Alpha Agency', Setting::get('agency_name'));
        Agencies::activate(Agencies::find('beta'));
        $this->assertSame(0, User::count());
        $this->assertSame('Beta Agency', Setting::get('agency_name'));

        // Each address opens its own agency: Beta has no administrator yet, so it shows the setup wizard.
        $this->get('http://alpha.test/login')->assertOk()->assertSee('Alpha Agency');
        $this->get('http://beta.test/')->assertRedirect('http://beta.test/setup');
        $this->get('http://unknown.test/login')->assertNotFound();

        $this->artisan('hris:agency:run', ['cmd' => 'hris:reminders'])->assertSuccessful();
    }
}
