{{-- Monthly column chart as inline SVG. $series: name => list of values (same length as $labels). --}}
@props(['labels' => [], 'series' => [], 'colors' => ['var(--primary)', 'var(--warn)', 'var(--ok)']])
@php
    $names = array_keys($series);
    $max = max(1, ...array_merge([0], ...array_values($series)));
    $h = 150; $w = 360; $pad = 28; $n = max(1, count($labels)); $slot = ($w - $pad) / $n; $k = max(1, count($names));
    $bw = min(18, ($slot - 6) / $k);
    $step = $max <= 5 ? 1 : (int) ceil($max / 4);
@endphp
<figure class="colchart">
    <svg viewBox="0 0 {{ $w }} {{ $h + 22 }}" role="img" aria-label="{{ implode(', ', $names) }} by month">
        @for ($t = 0; $t <= $max; $t += $step)
            @php $y = $h - $t / $max * ($h - 10); @endphp
            <line x1="{{ $pad }}" x2="{{ $w }}" y1="{{ $y }}" y2="{{ $y }}" stroke="var(--border)" stroke-width="1" />
            <text x="{{ $pad - 4 }}" y="{{ $y + 3 }}" text-anchor="end" font-size="12" fill="var(--muted)">{{ $t }}</text>
        @endfor
        @foreach ($labels as $i => $label)
            @foreach ($names as $j => $name)
                @php $v = $series[$name][$i] ?? 0; $bh = $v / $max * ($h - 10); $x = $pad + $i * $slot + ($slot - $bw * $k) / 2 + $j * $bw; @endphp
                <rect x="{{ $x }}" y="{{ $h - $bh }}" width="{{ max(1, $bw - 2) }}" height="{{ $bh }}" rx="2" fill="{{ $colors[$j % count($colors)] }}"><title>{{ $label }} — {{ $name }}: {{ $v }}</title></rect>
            @endforeach
            <text x="{{ $pad + $i * $slot + $slot / 2 }}" y="{{ $h + 14 }}" text-anchor="middle" font-size="12" fill="var(--muted)">{{ $label }}</text>
        @endforeach
    </svg>
    @if ($k > 1)<figcaption class="legend">@foreach ($names as $j => $name)<span><i style="background:{{ $colors[$j % count($colors)] }}"></i>{{ $name }}</span>@endforeach</figcaption>@endif
</figure>
