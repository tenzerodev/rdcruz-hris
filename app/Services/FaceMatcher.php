<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\FaceSample;
use InvalidArgumentException;

/**
 * Compares a 128-float face descriptor against enrolled samples.
 * Matching runs on the server so enrolled descriptors are never sent to devices.
 */
class FaceMatcher
{
    public const LENGTH = 128;

    public static function validate(mixed $descriptor): array
    {
        if (! is_array($descriptor) || count($descriptor) !== self::LENGTH) {
            throw new InvalidArgumentException('Face descriptor must have '.self::LENGTH.' values.');
        }

        return array_map(function ($v) {
            if (! is_numeric($v) || ! is_finite((float) $v) || abs((float) $v) > 1.5) {
                throw new InvalidArgumentException('Face descriptor contains an invalid value.');
            }

            return (float) $v;
        }, array_values($descriptor));
    }

    public static function distance(array $a, array $b): float
    {
        $sum = 0.0;
        for ($i = 0; $i < self::LENGTH; $i++) {
            $d = $a[$i] - $b[$i];
            $sum += $d * $d;
        }

        return sqrt($sum);
    }

    /**
     * Best match among recognizable employees (or only $only).
     *
     * @return array{employee: ?Employee, distance: float, matched: bool}
     */
    public function match(array $descriptor, ?Employee $only = null, ?int $exceptEmployeeId = null): array
    {
        $query = FaceSample::query()
            ->whereHas('employee', fn ($q) => $q->where('is_active', true)->whereNotNull('consent_at'));

        if ($only) {
            $query->where('employee_id', $only->id);
        }
        if ($exceptEmployeeId) {
            $query->where('employee_id', '!=', $exceptEmployeeId);
        }

        $best = ['id' => null, 'distance' => INF];
        foreach ($query->select(['id', 'employee_id', 'descriptor'])->cursor() as $sample) {
            $d = self::distance($descriptor, $sample->descriptor);
            if ($d < $best['distance']) {
                $best = ['id' => $sample->employee_id, 'distance' => $d];
            }
        }

        $matched = $best['id'] !== null && $best['distance'] <= config('dtr.match_threshold');

        return [
            'employee' => $matched ? Employee::find($best['id']) : null,
            'distance' => is_finite($best['distance']) ? round($best['distance'], 4) : 9.0,
            'matched' => $matched,
        ];
    }
}
