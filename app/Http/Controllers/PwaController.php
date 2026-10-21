<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

/** Lets employees install the HRIS on their phone's home screen like an app. */
class PwaController extends Controller
{
    public function manifest()
    {
        $agency = (string) Setting::get('agency_name', 'HRIS');

        return response()->json([
            'name' => trim('HRIS · '.$agency, ' ·'),
            'short_name' => 'HRIS',
            'description' => 'Time in, leave, payslips and requests for '.$agency,
            'start_url' => '/?source=app',
            'scope' => '/',
            'display' => 'standalone',
            'background_color' => '#ffffff',
            'theme_color' => '#1d4ed8',
            'icons' => [
                ['src' => route('pwa.icon', 192), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => route('pwa.icon', 512), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
            ],
            'shortcuts' => [
                ['name' => 'Time in / out', 'url' => '/me/punch'],
                ['name' => 'Leave', 'url' => '/me/leave'],
                ['name' => 'Payslips', 'url' => '/me/payslips'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json']);
    }

    /** The agency logo on a white square, or the default icon when there is no logo. */
    public function icon(int $size)
    {
        abort_unless(in_array($size, [180, 192, 512], true), 404);
        $logo = Setting::get('logo_path');
        $disk = Storage::disk('public');
        if (! $logo || ! $disk->exists($logo) || ! function_exists('imagecreatefromstring')) {
            return response()->file(public_path('images/app-icon-'.($size === 512 ? 512 : 192).'.png'), ['Cache-Control' => 'public, max-age=86400']);
        }

        $cached = 'app-icons/'.$size.'-'.substr(md5($logo.$disk->lastModified($logo)), 0, 10).'.png';
        if (! $disk->exists($cached)) {
            $src = @imagecreatefromstring($disk->get($logo));
            abort_unless($src, 404);
            $im = imagecreatetruecolor($size, $size);
            imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));
            $inner = (int) round($size * 0.78); // keep the logo inside the maskable safe zone
            [$w, $h] = [imagesx($src), imagesy($src)];
            $scale = min($inner / $w, $inner / $h);
            [$nw, $nh] = [(int) round($w * $scale), (int) round($h * $scale)];
            imagecopyresampled($im, $src, (int) (($size - $nw) / 2), (int) (($size - $nh) / 2), 0, 0, $nw, $nh, $w, $h);
            ob_start();
            imagepng($im);
            $disk->put($cached, ob_get_clean());
        }

        return response()->file($disk->path($cached), ['Content-Type' => 'image/png', 'Cache-Control' => 'public, max-age=86400']);
    }

    public function offline()
    {
        return view('offline');
    }
}
