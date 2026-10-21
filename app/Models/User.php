<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'username', 'email', 'password', 'role', 'must_change_password', 'disabled_at', 'notify_email'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'disabled_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'notify_email' => 'boolean',
        ];
    }

    /** Email for notices: the employee's 201 email, else the account's own. */
    public function routeNotificationForMail(): ?string
    {
        return $this->employee?->email ?: $this->email;
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    /** System administrator (all areas, user accounts, backups). */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /** Any HR-office role that uses the admin side (HR, records, payroll, timekeeper, auditor, admin). */
    public function isStaff(): bool
    {
        return $this->role !== 'employee' && isset(config('hris.roles')[$this->role]);
    }

    public function roleLabel(): string
    {
        return config("hris.roles.{$this->role}.label", ucfirst((string) $this->role));
    }

    /** Can change records in this area. */
    public function canManage(string $area): bool
    {
        $manage = config("hris.roles.{$this->role}.manage", []);

        return in_array('*', $manage, true) || in_array($area, $manage, true);
    }

    /** Can at least look at this area (read-only roles included). */
    public function canSee(string $area): bool
    {
        $view = config("hris.roles.{$this->role}.view", []);

        return $this->canManage($area) || in_array('*', $view, true) || in_array($area, $view, true);
    }

    public function hasTwoFactor(): bool
    {
        return $this->two_factor_confirmed_at !== null && $this->two_factor_secret;
    }

    /** Has people reporting to them, so they act on their requests. */
    public function isSupervisor(): bool
    {
        return $this->employee !== null && $this->employee->subordinates()->where('is_active', true)->exists();
    }

    /** HR (people managers), or the employee's direct supervisor. */
    public function canActFor(Employee $employee): bool
    {
        return $this->canManage('people') || ($this->employee && $employee->supervisor_id === $this->employee->id);
    }

    /** Can see this employee's records (self, supervisor, HR staff with access to 201 files). */
    public function canView(Employee $employee): bool
    {
        return $this->canSee('people') || $this->canActFor($employee) || $this->employee?->id === $employee->id;
    }

    /** Where to land after signing in. */
    public function homeUrl(): string
    {
        return $this->isStaff() ? '/admin' : '/me';
    }
}
