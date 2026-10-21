<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Holiday;
use App\Models\Setting;
use App\Models\User;
use App\Support\Install;
use App\Support\PhHolidays;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

/** First-run setup: agency details and the first system administrator. Closed once an admin exists. */
class SetupController extends Controller
{
    public function show()
    {
        abort_unless(Install::needed(), 404);

        return view('setup', ['year' => now()->year]);
    }

    public function store(Request $request)
    {
        abort_unless(Install::needed(), 404);

        $data = $request->validate([
            'agency_name' => ['required', 'string', 'max:200'],
            'agency_address' => ['nullable', 'string', 'max:250'],
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
            'hr_email' => ['nullable', 'email', 'max:150'],
            'name' => ['required', 'string', 'max:150'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:users,username'],
            'email' => ['nullable', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'holidays' => ['nullable', 'boolean'],
        ]);

        $user = DB::transaction(function () use ($data, $request) {
            $settings = [
                'agency_name' => $data['agency_name'],
                'agency_address' => $data['agency_address'] ?? '',
                'hr_email' => $data['hr_email'] ?? '',
                'notifications_enabled' => ! empty($data['hr_email']) ? '1' : '0',
            ];
            if ($request->hasFile('logo')) {
                $settings['logo_path'] = $request->file('logo')->store('branding', 'public');
            }
            Setting::put($settings);

            if ($request->boolean('holidays')) {
                foreach ([now()->year, now()->year + 1] as $year) {
                    foreach (PhHolidays::regular($year) as $date => $name) {
                        Holiday::firstOrCreate(['date' => $date], ['name' => $name, 'type' => 'regular']);
                    }
                }
            }

            $user = User::create([
                'name' => $data['name'], 'username' => $data['username'], 'email' => $data['email'] ?? null,
                'password' => $data['password'], 'role' => 'admin',
            ]);
            $user->forceFill(['password_changed_at' => now()])->save();

            return $user;
        });

        Cache::forever('hris.installed', true);
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('last_activity', now()->getTimestamp());
        AuditLog::record('system.setup_completed', $user, ['agency' => $data['agency_name']]);

        return redirect()->route('admin.system.index')->with('status', 'Setup complete. Check the items below, then add your employees (Import data, or Employees → Add).');
    }
}
