<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IpcrItem extends Model
{
    protected $fillable = ['ipcr_id', 'category', 'output', 'success_indicator', 'accomplishment', 'self_q', 'self_e', 'self_t', 'q', 'e', 't', 'remarks', 'sort'];

    public function ipcr(): BelongsTo
    {
        return $this->belongsTo(Ipcr::class);
    }

    /** Mean of the Q, E and T given (blank dimensions are skipped, as allowed by SPMS). */
    public function average(bool $self = false): ?float
    {
        $p = $self ? 'self_' : '';
        $vals = array_filter([$this->{$p.'q'}, $this->{$p.'e'}, $this->{$p.'t'}], fn ($v) => $v !== null);

        return $vals ? round(array_sum($vals) / count($vals), 2) : null;
    }
}
