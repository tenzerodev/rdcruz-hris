<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Punch;
use App\Models\Setting;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class PunchRecorder
{
    /**
     * Pick the next punch type for today.
     * Supports both 4-punch days (with lunch) and 2-punch days (AM arrival + PM departure).
     */
    public function nextType(Employee $employee, CarbonInterface $now): ?string
    {
        $done = Punch::where('employee_id', $employee->id)
            ->whereDate('work_date', $now->toDateString())
            ->pluck('type')->all();
        $hours = $employee->officialHours();
        $time = $now->format('H:i');

        if ($employee->punchesOncePerDay()) {
            return $this->nextOnceADay($done, $hours, $time);
        }

        if (! $done) {
            // First punch of the day: morning arrival, or afternoon arrival for a PM half-day.
            return $time < $hours['am_out'] ? 'am_in' : 'pm_in';
        }

        if (in_array('pm_out', $done, true)) {
            return null; // day complete
        }

        if (in_array('pm_in', $done, true)) {
            return 'pm_out';
        }

        if (in_array('am_out', $done, true)) {
            return 'pm_in';
        }

        // Only am_in so far: lunch break before PM arrival time, otherwise going home.
        return $time < $hours['pm_in'] ? 'am_out' : 'pm_out';
    }

    /**
     * Once a day: one time in, then one time out. The time in is stored as AM or PM arrival and the
     * time out as AM or PM departure by the clock, so half days still show correctly on Form 48.
     *
     * @param  list<string>  $done
     * @param  array{am_in: string, am_out: string, pm_in: string, pm_out: string}  $hours
     */
    private function nextOnceADay(array $done, array $hours, string $time, ?string $intent = null): ?string
    {
        $hasIn = (bool) array_intersect($done, Punch::ARRIVALS);
        $hasOut = (bool) array_intersect($done, Punch::DEPARTURES);

        if ($hasOut) {
            return null;
        }
        $wantsOut = $intent !== null ? in_array($intent, Punch::DEPARTURES, true) : $hasIn;
        if (! $wantsOut) {
            if ($hasIn) {
                throw new RuntimeException('Time In already recorded today.');
            }

            return $time < $hours['am_out'] ? 'am_in' : 'pm_in';
        }

        // Leaving before the afternoon session starts counts as a morning-only day.
        return $time < $hours['pm_in'] && ! in_array('pm_in', $done, true) ? 'am_out' : 'pm_out';
    }

    /**
     * @param  array{source: string, type?: ?string, kiosk_id?: ?int, distance?: ?float, liveness?: bool,
     *               latitude?: ?float, longitude?: ?float, accuracy?: ?float, snapshot?: ?string,
     *               ip?: ?string, user_agent?: ?string}  $data
     */
    public function record(Employee $employee, array $data, ?CarbonInterface $now = null): Punch
    {
        $now ??= now();

        $last = Punch::where('employee_id', $employee->id)->latest('punched_at')->first();
        if ($last && $last->punched_at->diffInSeconds($now, true) < config('dtr.duplicate_window_seconds')) {
            throw new RuntimeException("Already recorded {$last->label} at {$last->punched_at->format('g:i A')}.");
        }

        $type = ($data['type'] ?? null) ?: null;
        if ($employee->punchesOncePerDay()) {
            // A chosen punch type only says "in" or "out"; the stored type follows the clock.
            $done = Punch::where('employee_id', $employee->id)
                ->whereDate('work_date', $now->toDateString())
                ->pluck('type')->all();
            $type = $this->nextOnceADay($done, $employee->officialHours(), $now->format('H:i'), $type);
        } else {
            $type ??= $this->nextType($employee, $now);
        }
        if ($type === null) {
            throw new RuntimeException('You have completed all punches for today.');
        }
        if (! in_array($type, Punch::TYPES, true)) {
            throw new RuntimeException('Invalid punch type.');
        }

        $exists = Punch::where('employee_id', $employee->id)
            ->whereDate('work_date', $now->toDateString())
            ->where('type', $type)->first();
        if ($exists) {
            throw new RuntimeException($employee->punchLabel($type)." already recorded at {$exists->punched_at->format('g:i A')}.");
        }

        return Punch::create([
            'employee_id' => $employee->id,
            'work_date' => $now->toDateString(),
            'type' => $type,
            'punched_at' => $now,
            'source' => $data['source'],
            'kiosk_id' => $data['kiosk_id'] ?? null,
            'issued_device_id' => $data['issued_device_id'] ?? null,
            'distance' => $data['distance'] ?? null,
            'liveness' => $data['liveness'] ?? false,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'accuracy' => $data['accuracy'] ?? null,
            'snapshot_path' => isset($data['snapshot']) ? $this->storeSnapshot($data['snapshot'], $now) : null,
            'ip' => $data['ip'] ?? null,
            'user_agent' => isset($data['user_agent']) ? Str::limit($data['user_agent'], 250, '') : null,
        ]);
    }

    /** Save a base64 JPEG data URL to private storage. Returns the path or null if invalid. */
    public function storeSnapshot(string $dataUrl, CarbonInterface $now): ?string
    {
        if (! preg_match('#^data:image/jpeg;base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $m)) {
            return null;
        }
        $bytes = base64_decode($m[1], true);
        if ($bytes === false || strlen($bytes) > 400_000 || ! str_starts_with($bytes, "\xFF\xD8")) {
            return null;
        }
        if (@getimagesizefromstring($bytes) === false) {
            return null;
        }

        $path = 'snapshots/'.$now->format('Y/m/d').'/'.Str::uuid().'.jpg';
        Storage::disk('local')->put($path, $bytes);

        return $path;
    }

    /** Distance in meters between two coordinates (haversine). */
    public static function metersBetween(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /** Throws if geofencing is on and the location is missing or outside the allowed radius. */
    public function assertInsideGeofence(?float $lat, ?float $lng, ?float $accuracy): void
    {
        if (Setting::get('geofence_enabled') !== '1') {
            return;
        }
        $centerLat = Setting::get('geofence_lat');
        $centerLng = Setting::get('geofence_lng');
        if ($centerLat === '' || $centerLng === '' || $centerLat === null || $centerLng === null) {
            return;
        }
        if ($lat === null || $lng === null) {
            throw new RuntimeException('Location is required. Please allow location access and try again.');
        }

        $radius = (float) Setting::get('geofence_radius', '150');
        $meters = self::metersBetween($lat, $lng, (float) $centerLat, (float) $centerLng);
        // Give the benefit of GPS accuracy, capped so a very rough fix can't pass from far away.
        $slack = min((float) ($accuracy ?? 0), 100);
        if ($meters - $slack > $radius) {
            throw new RuntimeException('You are about '.round($meters).' m from the office. Punching from here is not allowed.');
        }
    }
}
