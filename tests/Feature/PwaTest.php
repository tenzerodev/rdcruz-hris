<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PwaTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_icons_and_offline_page(): void
    {
        Setting::put(['agency_name' => 'CITEM']);
        $this->get('/manifest.webmanifest')->assertOk()->assertJsonPath('name', 'HRIS · CITEM')->assertJsonPath('display', 'standalone');
        $this->get('/app-icon/192.png')->assertOk();
        $this->get('/app-icon/64.png')->assertNotFound();
        $this->get('/offline')->assertOk()->assertSee("You're offline", false);
        $this->assertFileExists(public_path('sw.js'));

        // With a logo, the icon is made from it.
        Storage::fake('public');
        Storage::disk('public')->put('logo.png', UploadedFile::fake()->image('logo.png', 300, 120)->getContent());
        Setting::put(['logo_path' => 'logo.png']);
        $this->get('/app-icon/512.png')->assertOk()->assertHeader('content-type', 'image/png');
        $this->assertCount(1, Storage::disk('public')->files('app-icons'));
    }
}
