<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    public function edit()
    {
        return view('admin.settings', ['s' => Setting::allValues()]);
    }

    /** Upload or remove the agency logo (PNG/JPG/WebP; shown on printouts, sidebar and sign-in). */
    public function logo(Request $request)
    {
        if ($request->boolean('remove')) {
            if ($old = Setting::get('logo_path')) {
                Storage::disk('public')->delete($old);
            }
            Setting::put(['logo_path' => '']);
            AuditLog::record('settings.logo_removed');

            return back()->with('status', 'Logo removed.');
        }
        $request->validate(['logo' => ['required', 'file', 'mimes:png,jpg,jpeg,webp', 'max:1024', 'dimensions:min_width=64,min_height=64,max_width=3000,max_height=3000']],
            ['logo.mimes' => 'Upload a PNG, JPG or WebP image.', 'logo.max' => 'The logo must be 1 MB or smaller.']);
        $old = Setting::get('logo_path');
        $path = $request->file('logo')->store('branding', 'public');
        Setting::put(['logo_path' => $path]);
        if ($old) {
            Storage::disk('public')->delete($old);
        }
        AuditLog::record('settings.logo_updated');

        return back()->with('status', 'Logo updated. It now appears on all printouts.');
    }

    public function update(Request $request)
    {
        $time = ['required', 'date_format:H:i'];
        $data = $request->validate([
            'agency_name' => ['required', 'string', 'max:200'],
            'agency_address' => ['nullable', 'string', 'max:250'],
            'am_in' => $time, 'am_out' => $time, 'pm_in' => $time, 'pm_out' => $time,
            'grace_minutes' => ['required', 'integer', 'min:0', 'max:60'],
            'punch_mode' => ['required', 'in:four,two'],
            'saturday_hours' => ['nullable', 'string', 'max:50'],
            'default_supervisor' => ['nullable', 'string', 'max:150'],
            'geofence_lat' => ['nullable', 'required_if:geofence_enabled,1', 'numeric', 'between:-90,90'],
            'geofence_lng' => ['nullable', 'required_if:geofence_enabled,1', 'numeric', 'between:-180,180'],
            'geofence_radius' => ['required', 'integer', 'min:20', 'max:5000'],
            'hr_email' => ['nullable', 'string', 'max:300', function ($attr, $value, $fail) {
                foreach (array_filter(array_map('trim', explode(',', (string) $value))) as $email) {
                    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $fail("“{$email}” is not a valid email address.");
                    }
                }
            }],
            'ot_min_minutes' => ['required', 'integer', 'min:0', 'max:480'],
            'flexi_start' => $time, 'flexi_end' => $time,
            'required_minutes' => ['required', 'integer', 'min:60', 'max:720'],
            'lunch_minutes' => ['required', 'integer', 'min:0', 'max:120'],
            'ot_rounding' => ['required', 'integer', 'in:1,5,10,15,30,60'],
            'ot_coc_weekday_rate' => ['required', 'numeric', 'min:0', 'max:3'],
            'ot_coc_restday_rate' => ['required', 'numeric', 'min:0', 'max:3'],
            'ot_coc_monthly_cap' => ['required', 'numeric', 'min:0', 'max:200'],
            'attendance_start_date' => ['nullable', 'date'],
            'agency_head_name' => ['nullable', 'string', 'max:150'],
            'agency_head_title' => ['nullable', 'string', 'max:150'],
            'hr_head_name' => ['nullable', 'string', 'max:150'],
            'hr_head_title' => ['nullable', 'string', 'max:150'],
            'agency_tin' => ['nullable', 'string', 'max:30'],
        ]);
        $data['attendance_start_date'] = $data['attendance_start_date'] ?? '';
        foreach (['agency_head_name', 'agency_head_title', 'hr_head_name', 'hr_head_title', 'agency_tin'] as $k) {
            $data[$k] = $data[$k] ?? '';
        }

        if ($data['flexi_start'] >= $data['flexi_end']) {
            return back()->withInput()->withErrors(['flexi_start' => 'The earliest time-in must be before the latest time-in.']);
        }
        if (! ($data['am_in'] < $data['am_out'] && $data['am_out'] <= $data['pm_in'] && $data['pm_in'] < $data['pm_out'])) {
            return back()->withInput()->withErrors(['am_in' => 'Official hours must be in order: AM in < AM out ≤ PM in < PM out.']);
        }

        foreach (['work_saturday', 'half_day_absence_as_undertime', 'geofence_enabled', 'ot_requires_approval', 'ot_count_early_arrival', 'ot_coc_enabled', 'ot_weekend_full_week', 'ot_full_week_holidays', 'notifications_enabled'] as $flag) {
            $data[$flag] = $request->boolean($flag) ? '1' : '0';
        }
        $data = array_map(fn ($v) => (string) ($v ?? ''), $data);

        Setting::put($data);
        AuditLog::record('settings.updated', null, $data);

        return back()->with('status', 'Settings saved.');
    }
}
