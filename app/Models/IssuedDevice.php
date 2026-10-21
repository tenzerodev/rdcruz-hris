<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class IssuedDevice extends Model
{
    protected $fillable = ['employee_id', 'name', 'property_no', 'serial_no', 'is_active', 'created_by'];

    protected $hidden = ['token_hash', 'activation_hash'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'activation_expires_at' => 'datetime',
            'activated_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** Makes a new one-time activation code (valid 3 days) and returns it; the old registration stops working. */
    public function newActivationCode(): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $this->forceFill([
            'activation_hash' => hash('sha256', $code),
            'activation_expires_at' => now()->addDays(3),
            'token_hash' => null,
            'activated_at' => null,
            'activated_ip' => null,
            'user_agent' => null,
        ])->save();

        return substr($code, 0, 4).'-'.substr($code, 4);
    }

    /** Registers the browser: returns the secret token to keep in the device cookie. */
    public function activate(string $ip, ?string $userAgent): string
    {
        $token = Str::random(48);
        $this->forceFill([
            'token_hash' => hash('sha256', $token),
            'activation_hash' => null,
            'activation_expires_at' => null,
            'activated_at' => now(),
            'activated_ip' => $ip,
            'last_ip' => $ip,
            'user_agent' => Str::limit((string) $userAgent, 250, ''),
        ])->save();

        return $token;
    }

    public static function findByCode(string $code): ?self
    {
        $clean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));

        return static::where('activation_hash', hash('sha256', $clean))->where('is_active', true)
            ->where('activation_expires_at', '>', now())->first();
    }

    /**
     * A short, readable name for the browser the code was entered in, e.g. "Chrome 141 on Windows".
     * The browser reports this itself, so it is a guide for HR, not proof.
     */
    public function getBrowserLabelAttribute(): ?string
    {
        $agent = (string) $this->user_agent;
        if ($agent === '') {
            return null;
        }

        $browser = match (true) {
            (bool) preg_match('~Edg(?:e|A|iOS)?/(\d+)~', $agent, $m) => 'Edge '.$m[1],
            (bool) preg_match('~(?:OPR|Opera)/(\d+)~', $agent, $m) => 'Opera '.$m[1],
            (bool) preg_match('~SamsungBrowser/(\d+)~', $agent, $m) => 'Samsung Internet '.$m[1],
            (bool) preg_match('~(?:Firefox|FxiOS)/(\d+)~', $agent, $m) => 'Firefox '.$m[1],
            (bool) preg_match('~(?:Chrome|CriOS)/(\d+)~', $agent, $m) => 'Chrome '.$m[1],
            (bool) preg_match('~Version/(\d+).*Safari/~', $agent, $m) => 'Safari '.$m[1],
            default => 'Unknown browser',
        };

        $system = match (true) {
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'iPhone') => 'iPhone',
            str_contains($agent, 'iPad') => 'iPad',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'CrOS') => 'ChromeOS',
            str_contains($agent, 'Mac OS X') || str_contains($agent, 'Macintosh') => 'Mac',
            str_contains($agent, 'Linux') => 'Linux',
            default => null,
        };

        return $system ? "{$browser} on {$system}" : $browser;
    }

    /** True when the code was entered on a phone or tablet, which is unlikely for an issued computer. */
    public function getRegisteredOnMobileAttribute(): bool
    {
        return (bool) preg_match('~iPhone|iPad|Android|Mobile~', (string) $this->user_agent);
    }

    public function getStatusAttribute(): string
    {
        return match (true) {
            ! $this->is_active => 'disabled',
            $this->activated_at !== null => 'active',
            $this->activation_expires_at?->isFuture() => 'waiting',
            default => 'expired',
        };
    }
}
