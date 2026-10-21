<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Kiosk extends Model
{
    protected $fillable = ['name', 'location', 'is_active'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'last_seen_at' => 'datetime'];
    }

    /** Issue a new device token (invalidates any device previously activated). */
    public function issueToken(): string
    {
        $token = Str::random(48);
        $this->forceFill(['token_hash' => hash('sha256', $token)])->save();

        return $token;
    }

    public function tokenMatches(string $token): bool
    {
        return $this->token_hash !== null && hash_equals($this->token_hash, hash('sha256', $token));
    }
}
