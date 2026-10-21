{{-- Shared camera block for kiosk and personal punching. --}}
<div class="cam">
    <video autoplay playsinline muted></video>
    <canvas aria-hidden="true"></canvas>
    <div class="guide" aria-hidden="true"></div>
    <div class="result" data-result hidden role="alert">
        <div>
            <div class="r-title" data-r-title></div>
            <div class="r-main" data-r-main></div>
            <div class="r-sub" data-r-sub></div>
        </div>
    </div>
</div>
<div class="status" aria-live="polite">
    <div data-status>Starting…</div>
    <div data-sub></div>
    <button class="btn btn-sm" data-retry hidden style="margin-top:8px">Try again</button>
</div>
<div class="chips" role="group" aria-label="Punch type">
    @php($onceADay = isset($employee) && $employee->punchesOncePerDay())
    <button type="button" class="chip" data-type="" aria-pressed="true">Auto{{ isset($next) && $next ? ' ('.\App\Models\Punch::labelFor($next, $onceADay).')' : '' }}</button>
    @foreach ($onceADay ? ['am_in' => 'Time In', 'pm_out' => 'Time Out'] : \App\Models\Punch::LABELS as $type => $label)
        <button type="button" class="chip" data-type="{{ $type }}" aria-pressed="false">{{ $label }}</button>
    @endforeach
</div>
