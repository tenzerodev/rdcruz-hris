{{-- Horizontal bars: label => value. --}}
@props(['data' => [], 'unit' => '', 'color' => 'var(--primary)'])
@php $max = max(1, ...array_values($data ?: [0])); @endphp
<div class="hbars">
    @forelse ($data as $label => $value)
        <div class="hbar"><span class="hbar-label" title="{{ $label }}">{{ $label }}</span>
            <span class="hbar-track"><span class="hbar-fill" style="width:{{ $value ? max(2, round($value / $max * 100, 1)) : 0 }}%;background:{{ $color }}"></span></span>
            <b class="hbar-value">{{ is_float($value) ? rtrim(rtrim(number_format($value, 1), '0'), '.') : $value }}{{ $unit }}</b></div>
    @empty <p class="muted small">No data.</p> @endforelse
</div>
