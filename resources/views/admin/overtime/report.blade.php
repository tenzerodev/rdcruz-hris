@php
    $hm = fn (int $min) => $min ? intdiv($min, 60).':'.str_pad($min % 60, 2, '0', STR_PAD_LEFT) : '';
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Overtime Report · {{ $month->format('F Y') }}</title>
    <style>
        @page { size: portrait; margin: 12mm; }
        body { margin: 0; font: 10pt/1.35 system-ui, -apple-system, "Segoe UI", sans-serif; color: #000; background: #e5e7eb; }
        .toolbar { position: sticky; top: 0; background: #111827; color: #fff; padding: 10px 16px; display: flex; gap: 12px; align-items: center; flex-wrap: wrap; font-size: 14px; }
        .toolbar button { background: #2563eb; color: #fff; border: 0; padding: 8px 14px; border-radius: 6px; font: inherit; font-weight: 600; cursor: pointer; }
        .page { background: #fff; max-width: 190mm; margin: 12px auto; padding: 12mm; box-shadow: 0 2px 10px rgba(0,0,0,.15); }
        h1 { font-size: 13pt; margin: 0; text-align: center; } .sub { text-align: center; margin-bottom: 8mm; }
        .emp { break-inside: avoid; margin-bottom: 8mm; }
        .emp h2 { font-size: 11pt; margin: 0 0 1mm; }
        table { width: 100%; border-collapse: collapse; margin-top: 2mm; }
        th, td { border: 1px solid #000; padding: 1mm 2mm; text-align: center; font-variant-numeric: tabular-nums; }
        th { background: #f3f4f6; font-size: 8.5pt; } td.l { text-align: left; }
        tr.total td { font-weight: bold; }
        .muted { color: #555; font-size: 8.5pt; }
        .warn { color: #b45309; }
        .sigs { display: flex; gap: 20mm; margin-top: 14mm; }
        .sigs div { flex: 1; text-align: center; border-top: 1px solid #000; padding-top: 1mm; font-size: 9pt; }
        @media print { body { background: #fff; } .toolbar { display: none; } .page { margin: 0; box-shadow: none; padding: 0; max-width: none; } th { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
    </style>
</head>
<body>
<div class="toolbar"><button onclick="window.print()">Print</button><span>{{ $records->count() }} employee(s) with overtime · hours shown as h:mm</span></div>
<div class="page">
    <x-print-header />
    <h1>OVERTIME REPORT</h1>
    <div class="sub">{{ $settings['agency_name'] }}<br>For the month of <b>{{ strtoupper($month->format('F Y')) }}</b></div>

    @forelse ($records as $r)
        @php $e = $r['employee']; @endphp
        <div class="emp">
            <h2>{{ $e->full_name }}</h2>
            <div class="muted">{{ $e->employee_no }} · {{ $e->position }} · {{ $e->office }} · {{ $r['hours_label'] }}</div>
            <table>
                <thead><tr><th>Date</th><th>Day type</th><th>Arrival</th><th>Departure</th><th>Rendered</th><th>Credited</th>@if ($rules['coc'])<th>COC earned</th>@endif<th>Authority</th></tr></thead>
                <tbody>
                @foreach ($r['rows'] as $row)
                    @continue(! $row['ot_actual'])
                    <tr>
                        <td>{{ $row['date']->format('M j, D') }}</td>
                        <td>{{ $row['is_workday'] ? 'Work day' : 'Rest day / holiday' }}</td>
                        <td>{{ $row['am_in'] ?? $row['pm_in'] }}</td>
                        <td>{{ $row['pm_out'] ?? $row['am_out'] }}</td>
                        <td>{{ $hm($row['ot_actual']) }}</td>
                        <td @class(['warn' => $row['ot_credited'] < $row['ot_actual']])>{{ $hm($row['ot_credited']) ?: '0:00' }}</td>
                        @if ($rules['coc'])<td>{{ $hm($row['ot_coc']) }}</td>@endif
                        <td class="l">{{ $row['ot_authorization'] ? ($row['ot_authorization']->reference_no ?: 'Approved') : ($rules['requires_approval'] ? 'None' : '—') }}
                            @if ($row['ot_reason'])<div class="muted warn">{{ $row['ot_reason'] }}</div>@endif</td>
                    </tr>
                @endforeach
                <tr class="total">
                    <td colspan="4" class="l">TOTAL</td>
                    <td>{{ $hm($r['overtime']['actual']) ?: '0:00' }}</td>
                    <td>{{ $hm($r['overtime']['credited']) ?: '0:00' }}</td>
                    @if ($rules['coc'])<td>{{ $hm($r['overtime']['coc_capped']) ?: '0:00' }}@if ($r['overtime']['coc_capped'] < $r['overtime']['coc'])<br><span class="muted">capped at {{ $rules['coc_cap'] }} h</span>@endif</td>@endif
                    <td></td>
                </tr>
                </tbody>
            </table>
            @if ($r['overtime']['unapproved'])
                <div class="muted warn">{{ $hm($r['overtime']['unapproved']) }} rendered without (or beyond) an overtime authority is not credited.</div>
            @endif
        </div>
    @empty
        <p>No overtime rendered this month.</p>
    @endforelse

    <p class="muted">Rules: work days count time after the required time-out (flexi: 8 hours + lunch from time-in; fixed: official departure){{ $rules['early'] ? ' and before official arrival' : '' }}; rest days and holidays count all hours worked (less lunch). Less than {{ $rules['min'] / 60 }} hours in a day can't be claimed.@if ($rules['full_week']) Weekend overtime needs no leave or absence Monday–Friday of that week.@endif
        @if ($rules['coc']) COC: ×{{ $rules['weekday_rate'] + 0 }} on work days, ×{{ $rules['restday_rate'] + 0 }} on rest days/holidays{{ $rules['coc_cap'] ? ', max '.($rules['coc_cap'] + 0).' h per month' : '' }}.@endif</p>
    <div class="sigs"><div>Prepared by</div><div>Certified correct</div><div>Approved</div></div>
    <x-verify-stamp type="overtime_report" :key="'overtime:'.$month->format('Y-m').':'.request('office').':'.request('employee_id')" :title="'Overtime Report '.$month->format('F Y').(request('office') ? ' — '.request('office') : '')" :summary="[
        'Month' => $month->format('F Y'), 'Office' => request('office') ?: 'All', 'Employees with overtime' => $records->count(),
        'Total rendered (h)' => round($records->sum(fn ($r) => $r['overtime']['actual']) / 60, 2),
        'Total credited (h)' => round($records->sum(fn ($r) => $r['overtime']['credited']) / 60, 2),
        'Names' => $records->map(fn ($r) => $r['employee']->full_name)->join('; '),
    ]" style="margin-top:8mm" />
</div>
</body>
</html>
