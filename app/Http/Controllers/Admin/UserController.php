<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Sign-in accounts and roles. Only system administrators reach this page. */
class UserController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $users = User::with('employee:id,user_id,last_name,first_name,office')
            ->when($request->query('role'), fn ($w, $role) => $w->where('role', $role))
            ->when($request->query('role') === null && ! $q, fn ($w) => $w->where('role', '!=', 'employee'))
            ->when($q, fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', "%{$q}%")->orWhere('username', 'like', "%{$q}%")))
            ->orderByRaw("case when role = 'employee' then 1 else 0 end")->orderBy('name')
            ->paginate(40)->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'q' => $q,
            'roles' => config('hris.roles'),
            'areas' => config('hris.areas'),
            'unlinked' => Employee::where('is_active', true)->whereNull('user_id')->orderBy('last_name')->get(['id', 'last_name', 'first_name', 'employee_no']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:users,username'],
            'email' => ['nullable', 'email', 'max:150', 'unique:users,email'],
            'role' => ['required', Rule::in(array_keys(config('hris.roles')))],
            'employee_id' => ['nullable', Rule::exists('employees', 'id')->whereNull('user_id')],
        ]);
        $password = $this->temporaryPassword();
        $user = User::create([...collect($data)->except('employee_id')->all(), 'password' => $password, 'must_change_password' => true]);
        if (! empty($data['employee_id'])) {
            Employee::whereKey($data['employee_id'])->update(['user_id' => $user->id]);
        }
        AuditLog::record('user.created', $user, ['role' => $user->role]);

        return back()->with('status', "Account “{$user->username}” made. Temporary password: {$password} — give it to them privately; they must change it when they first sign in.");
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::in(array_keys(config('hris.roles')))],
        ]);
        if ($user->isAdmin() && $data['role'] !== 'admin') {
            $this->guardLastAdmin($user, 'change the role of');
        }
        if ($data['role'] === 'employee' && ! $user->employee) {
            throw ValidationException::withMessages(['role' => 'An employee account must be linked to an employee record. Link it from the employee’s profile instead.']);
        }
        $old = $user->role;
        $user->update($data);
        AuditLog::record('user.updated', $user, ['role' => [$old, $user->role]]);

        return back()->with('status', "Saved {$user->username}.");
    }

    public function resetPassword(User $user)
    {
        $password = $this->temporaryPassword();
        $user->forceFill(['password' => $password, 'must_change_password' => true])->save();
        AuditLog::record('user.password_reset', $user);

        return back()->with('status', "New temporary password for {$user->username}: {$password} — they must change it when they sign in.");
    }

    public function resetTwoFactor(User $user)
    {
        $user->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();
        AuditLog::record('user.2fa_reset', $user);

        return back()->with('status', "Two-step verification reset for {$user->username}. They can set it up again after signing in.");
    }

    public function toggle(Request $request, User $user)
    {
        if (! $user->disabled_at) {
            abort_if($user->is($request->user()), 422, 'You cannot disable your own account.');
            if ($user->isAdmin()) {
                $this->guardLastAdmin($user, 'disable');
            }
        }
        $user->forceFill(['disabled_at' => $user->disabled_at ? null : now()])->save();
        AuditLog::record($user->disabled_at ? 'user.disabled' : 'user.enabled', $user);

        return back()->with('status', $user->disabled_at ? "{$user->username} can no longer sign in." : "{$user->username} can sign in again.");
    }

    private function guardLastAdmin(User $user, string $action): void
    {
        $others = User::where('role', 'admin')->whereNull('disabled_at')->whereKeyNot($user->id)->exists();
        if (! $others) {
            throw ValidationException::withMessages(['role' => "You cannot {$action} the only system administrator. Make another administrator first."]);
        }
    }

    private function temporaryPassword(): string
    {
        // Easy to read aloud: no look-alike characters, always letters and numbers.
        $letters = fn (int $n) => collect(range(1, $n))->map(fn () => 'abcdefghjkmnpqrstuvwxyz'[random_int(0, 22)])->join('');

        return $letters(4).'-'.random_int(2000, 9999).'-'.$letters(4);
    }
}
