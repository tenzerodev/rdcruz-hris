@extends('layouts.app')
@section('title', 'Settings')
@section('content')
<h1 style="margin-bottom:16px">Settings</h1>
@php $logo = \App\Models\Setting::logoUrl(); @endphp
<section class="card" style="margin-bottom:16px">
    <div class="card-head"><h2>Agency logo</h2></div>
    <div style="display:flex;gap:18px;align-items:center;flex-wrap:wrap">
        <div style="width:88px;height:88px;border:1px dashed var(--border-strong);border-radius:12px;display:grid;place-items:center;background:#fff;overflow:hidden">
            @if ($logo)<img src="{{ $logo }}" alt="Current logo" style="max-width:100%;max-height:100%;object-fit:contain">@else<span class="small muted">No logo</span>@endif
        </div>
        <form method="post" action="{{ route('admin.settings.logo') }}" enctype="multipart/form-data" class="inline-form">@csrf
            <div class="field"><label for="logo">Upload logo (PNG, JPG or WebP, up to 1 MB)</label><input type="file" id="logo" name="logo" accept="image/png,image/jpeg,image/webp" required></div>
            <button class="btn btn-primary">Upload</button>
        </form>
        @if ($logo)
            <form method="post" action="{{ route('admin.settings.logo') }}" onsubmit="return confirm('Remove the logo?')">@csrf<input type="hidden" name="remove" value="1"><button class="btn btn-danger">Remove</button></form>
        @endif
    </div>
    <p class="hint">Shown on every printout (Form 48, Form 6, PDS, service record, certificates, travel orders, IPCR, reports), the menu and the sign-in page. A square image with a transparent or white background works best.</p>
</section>
<form method="post" action="{{ route('admin.settings.update') }}" class="grid grid-2">
    @csrf @method('put')
    <section class="card">
        <h2>Agency &amp; office hours</h2>
        <div class="field"><label for="agency_name">Agency / office name</label><input type="text" id="agency_name" name="agency_name" value="{{ old('agency_name', $s['agency_name']) }}" required></div>
        <div class="field"><label for="agency_address">Address (printed under the name)</label><input type="text" id="agency_address" name="agency_address" value="{{ old('agency_address', $s['agency_address']) }}"></div>
        <div class="row">
            @foreach (['am_in' => 'AM arrival', 'am_out' => 'AM departure', 'pm_in' => 'PM arrival', 'pm_out' => 'PM departure'] as $f => $l)
                <div class="field"><label for="{{ $f }}">{{ $l }}</label><input type="time" id="{{ $f }}" name="{{ $f }}" value="{{ old($f, $s[$f]) }}" required></div>
            @endforeach
        </div>
        <h2 style="margin-top:8px">Signatories &amp; employer details</h2>
        <div class="row">
            <div class="field"><label for="agency_head_name">Head of agency</label><input type="text" id="agency_head_name" name="agency_head_name" value="{{ old('agency_head_name', $s['agency_head_name']) }}"></div>
            <div class="field"><label for="agency_head_title">Title</label><input type="text" id="agency_head_title" name="agency_head_title" value="{{ old('agency_head_title', $s['agency_head_title']) }}"></div>
        </div>
        <div class="row">
            <div class="field"><label for="hr_head_name">HR head</label><input type="text" id="hr_head_name" name="hr_head_name" value="{{ old('hr_head_name', $s['hr_head_name']) }}"></div>
            <div class="field"><label for="hr_head_title">Title</label><input type="text" id="hr_head_title" name="hr_head_title" value="{{ old('hr_head_title', $s['hr_head_title']) }}"></div>
        </div>
        <div class="field"><label for="agency_tin">Agency TIN (for tax certificates)</label><input type="text" id="agency_tin" name="agency_tin" value="{{ old('agency_tin', $s['agency_tin']) }}" placeholder="000-000-000-000"></div>
        <div class="field"><label for="attendance_start_date">Attendance records start on</label><input type="date" id="attendance_start_date" name="attendance_start_date" value="{{ old('attendance_start_date', $s['attendance_start_date']) }}">
            <div class="hint">Days before this are never counted as absences (for example, before the HRIS went live). Blank = the first recorded time-in{{ ($first = \App\Services\DtrBuilder::trackingStart()) ? ' ('.$first->format('M j, Y').')' : '' }}.</div></div>
        <h2 style="margin-top:8px">Flexi-time</h2>
        <div class="row">
            <div class="field"><label for="flexi_start">Earliest time-in counted</label><input type="time" id="flexi_start" name="flexi_start" value="{{ old('flexi_start', $s['flexi_start']) }}" required></div>
            <div class="field"><label for="flexi_end">Latest time-in (late after)</label><input type="time" id="flexi_end" name="flexi_end" value="{{ old('flexi_end', $s['flexi_end']) }}" required></div>
            <div class="field"><label for="required_minutes">Required work (minutes)</label><input type="number" id="required_minutes" name="required_minutes" min="60" max="720" value="{{ old('required_minutes', $s['required_minutes']) }}" required></div>
            <div class="field"><label for="lunch_minutes">Lunch (minutes)</label><input type="number" id="lunch_minutes" name="lunch_minutes" min="0" max="120" value="{{ old('lunch_minutes', $s['lunch_minutes']) }}" required></div>
        </div>
        <p class="hint">Required time-out = time-in (no earlier than {{ $s['flexi_start'] }}) + required work + lunch. Arriving after the latest time-in is tardiness. The fixed hours above apply to employees set to "Fixed hours".</p>
        <div class="field"><label for="punch_mode">Default time in / time out</label>
            <select id="punch_mode" name="punch_mode">@foreach (\App\Models\Punch::MODES as $k => $l)<option value="{{ $k }}" @selected(old('punch_mode', $s['punch_mode']) === $k)>{{ $l }}</option>@endforeach</select>
            <div class="hint">Applies to employees without their own setting. Once a day: lunch is not punched and is deducted automatically.</div></div>
        <div class="field"><label for="grace_minutes">Grace period (minutes)</label><input type="number" id="grace_minutes" name="grace_minutes" min="0" max="60" value="{{ old('grace_minutes', $s['grace_minutes']) }}"><div class="hint">Arrivals up to this many minutes late are not counted as undertime. Use 0 if your agency has no grace period.</div></div>
        <label class="check"><input type="checkbox" name="half_day_absence_as_undertime" value="1" @checked($s['half_day_absence_as_undertime'] === '1')> Count a missing half-day (no AM or no PM punches) as undertime</label>
        <label class="check"><input type="checkbox" name="work_saturday" value="1" @checked($s['work_saturday'] === '1')> Saturday is a work day</label>
        <div class="field" style="margin-top:12px"><label for="saturday_hours">Saturday hours shown on Form 48</label><input type="text" id="saturday_hours" name="saturday_hours" value="{{ old('saturday_hours', $s['saturday_hours']) }}" placeholder="e.g. 8:00–12:00"></div>
        <div class="field"><label for="default_supervisor">Default "In Charge" (verifying officer)</label><input type="text" id="default_supervisor" name="default_supervisor" value="{{ old('default_supervisor', $s['default_supervisor']) }}"></div>
    </section>
    <div class="stack">
        <section class="card">
            <h2>Personal device geofence</h2>
            <p class="hint">When on, employees punching from their own phone must be within this radius of the office. Kiosks are not affected.</p>
            <label class="check"><input type="checkbox" name="geofence_enabled" value="1" @checked($s['geofence_enabled'] === '1')> Require location near the office</label>
            <div class="row" style="margin-top:12px">
                <div class="field"><label for="geofence_lat">Office latitude</label><input type="text" id="geofence_lat" name="geofence_lat" value="{{ old('geofence_lat', $s['geofence_lat']) }}" inputmode="decimal"></div>
                <div class="field"><label for="geofence_lng">Office longitude</label><input type="text" id="geofence_lng" name="geofence_lng" value="{{ old('geofence_lng', $s['geofence_lng']) }}" inputmode="decimal"></div>
            </div>
            <div class="field"><label for="geofence_radius">Radius (meters)</label><input type="number" id="geofence_radius" name="geofence_radius" min="20" max="5000" value="{{ old('geofence_radius', $s['geofence_radius']) }}"></div>
            <button type="button" class="btn btn-sm" id="use-location">Use my current location</button>
        </section>
        <section class="card">
            <h2>Overtime</h2>
            <label class="check"><input type="checkbox" name="ot_requires_approval" value="1" @checked($s['ot_requires_approval'] === '1')> Count overtime only when covered by an overtime authority</label>
            <label class="check"><input type="checkbox" name="ot_count_early_arrival" value="1" @checked($s['ot_count_early_arrival'] === '1')> Count time before official AM arrival as overtime (fixed hours only)</label>
            <label class="check"><input type="checkbox" name="ot_weekend_full_week" value="1" @checked($s['ot_weekend_full_week'] === '1')> Weekend overtime only if no leave or absence Mon–Fri of that week</label>
            <label class="check"><input type="checkbox" name="ot_full_week_holidays" value="1" @checked($s['ot_full_week_holidays'] === '1')> Apply the same rule to holiday overtime</label>
            <div class="row" style="margin-top:12px">
                <div class="field"><label for="ot_min_minutes">Minimum to claim (minutes)</label><input type="number" id="ot_min_minutes" name="ot_min_minutes" min="0" max="480" value="{{ old('ot_min_minutes', $s['ot_min_minutes']) }}"></div>
                <div class="field"><label for="ot_rounding">Round down to</label>
                    <select id="ot_rounding" name="ot_rounding">@foreach ([1 => 'exact minutes', 5 => '5 min', 10 => '10 min', 15 => '15 min', 30 => '30 min', 60 => '1 hour'] as $v => $l)<option value="{{ $v }}" @selected((string) $s['ot_rounding'] === (string) $v)>{{ $l }}</option>@endforeach</select></div>
            </div>
            <label class="check"><input type="checkbox" name="ot_coc_enabled" value="1" @checked($s['ot_coc_enabled'] === '1')> Compute compensatory overtime credits (COC)</label>
            <div class="row" style="margin-top:12px">
                <div class="field"><label for="ot_coc_weekday_rate">COC rate, work days</label><input type="number" step="0.25" id="ot_coc_weekday_rate" name="ot_coc_weekday_rate" value="{{ old('ot_coc_weekday_rate', $s['ot_coc_weekday_rate']) }}"></div>
                <div class="field"><label for="ot_coc_restday_rate">COC rate, rest days/holidays</label><input type="number" step="0.25" id="ot_coc_restday_rate" name="ot_coc_restday_rate" value="{{ old('ot_coc_restday_rate', $s['ot_coc_restday_rate']) }}"></div>
                <div class="field"><label for="ot_coc_monthly_cap">Monthly cap (hours)</label><input type="number" step="1" id="ot_coc_monthly_cap" name="ot_coc_monthly_cap" value="{{ old('ot_coc_monthly_cap', $s['ot_coc_monthly_cap']) }}"><div class="hint">0 = no cap</div></div>
            </div>
            <p class="hint">Defaults follow the CSC-DBM COC rules (1 hour per hour on work days, 1.5 on rest days and holidays, at most 40 hours a month). Check them against your agency's policy.</p>
        </section>
        <section class="card">
            <h2>Email notifications</h2>
            <label class="check"><input type="checkbox" name="notifications_enabled" value="1" @checked($s['notifications_enabled'] === '1')> Send emails for leave, overtime, travel and IPCR steps</label>
            <div class="field" style="margin-top:12px"><label for="hr_email">HR email(s)</label><input type="text" id="hr_email" name="hr_email" value="{{ old('hr_email', $s['hr_email']) }}" placeholder="hr@agency.gov.ph, records@agency.gov.ph"><div class="hint">Gets leave ready for final approval, requests from people without a supervisor, and the weekly contract-expiry digest. Employees and supervisors are emailed at the email on their 201 record. Mail server: <code>MAIL_*</code> in <code>.env</code>.</div></div>
        </section>
        <section class="card">
            <h2>Face matching</h2>
            <p class="small">Match threshold: <b>{{ config('dtr.match_threshold') }}</b> (set <code>DTR_MATCH_THRESHOLD</code> in <code>.env</code>; lower = stricter, 0.40–0.50 recommended).</p>
            <p class="small">Snapshots are kept for {{ config('dtr.snapshot_retention_days') }} days, then deleted by the scheduler.</p>
            <p class="small"><a href="{{ route('admin.audit') }}">View audit log →</a></p>
        </section>
        <div><button class="btn btn-primary">Save settings</button></div>
    </div>
</form>
@endsection
@push('scripts')
<script>
document.getElementById('use-location').addEventListener('click', () => {
  if (!navigator.geolocation) return;
  navigator.geolocation.getCurrentPosition((p) => {
    document.getElementById('geofence_lat').value = p.coords.latitude.toFixed(7);
    document.getElementById('geofence_lng').value = p.coords.longitude.toFixed(7);
  }, (e) => { document.getElementById('use-location').textContent = 'Location unavailable: ' + e.message; }, { enableHighAccuracy: true });
});
</script>
@endpush
