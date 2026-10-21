{{-- Agency letterhead for printouts: logo, Republic of the Philippines, agency name and address. --}}
@props(['compact' => false])
@php $logo = \App\Models\Setting::logoUrl(); @endphp
<div {{ $attributes->merge(['class' => 'lh'.($compact ? ' lh-compact' : '')]) }}>
    @if ($logo)<img class="lh-logo" src="{{ $logo }}" alt="">@endif
    <div class="lh-text">
        <div class="lh-rp">Republic of the Philippines</div>
        <div class="lh-agency">{{ \App\Models\Setting::get('agency_name') }}</div>
        @if (\App\Models\Setting::get('agency_address'))<div class="lh-addr">{{ \App\Models\Setting::get('agency_address') }}</div>@endif
    </div>
    @if ($logo)<span class="lh-logo lh-spacer" aria-hidden="true"></span>@endif
</div>
@once
<style>
    .lh { display: flex; align-items: center; justify-content: center; gap: 4mm; margin-bottom: 3mm; text-align: center; }
    .lh-logo { width: 18mm; height: 18mm; object-fit: contain; flex: none; }
    .lh-compact .lh-logo { width: 11mm; height: 11mm; }
    .lh-rp { font-size: 9pt; } .lh-agency { font-weight: bold; font-size: 11.5pt; text-transform: uppercase; } .lh-addr { font-size: 8.5pt; }
    .lh-compact .lh-rp { font-size: 7.5pt; } .lh-compact .lh-agency { font-size: 9pt; } .lh-compact .lh-addr { font-size: 7pt; }
</style>
@endonce
