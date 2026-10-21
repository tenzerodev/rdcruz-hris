@php
    $fmt = fn ($hhmm) => $hhmm ? \Carbon\Carbon::createFromFormat('H:i', $hhmm)->format('g:i') : '';
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CSC Form 48 · Daily Time Record</title>
    <style>
        @page { size: landscape; margin: 5mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: "Times New Roman", Times, serif; color: #000; background: #e5e7eb; }
        .toolbar { position: sticky; top: 0; background: #111827; color: #fff; padding: 10px 16px; display: flex; gap: 12px; align-items: center; flex-wrap: wrap; font-family: system-ui, sans-serif; font-size: 14px; z-index: 2; }
        .toolbar button, .toolbar a { background: #2563eb; color: #fff; border: 0; padding: 8px 14px; border-radius: 6px; font: inherit; font-weight: 600; cursor: pointer; text-decoration: none; }
        .toolbar a { background: #374151; }
        .sheet { background: #fff; width: 283mm; min-height: 196mm; margin: 12px auto; padding: 0; display: flex; gap: 0; box-shadow: 0 2px 10px rgba(0,0,0,.15); page-break-after: always; break-after: page; }
        .sheet:last-child { page-break-after: auto; break-after: auto; }
        .copy { flex: 1; padding: 3mm 7mm; font-size: 9pt; line-height: 1.15; }
        .copy + .copy { border-left: 1px dashed #999; }
        .form-no { font-size: 8pt; font-style: italic; }
        .title { text-align: center; font-weight: bold; font-size: 12.5pt; letter-spacing: .5px; margin-top: 1mm; }
        .circle { text-align: center; font-size: 8pt; margin-bottom: 2mm; }
        .name { text-align: center; font-weight: bold; font-size: 11pt; border-bottom: 1px solid #000; margin: 0 6mm; padding-top: 2mm; text-transform: uppercase; }
        .caption { text-align: center; font-size: 7.5pt; margin-bottom: 1.5mm; }
        .meta { font-size: 8.5pt; margin: 0 0 1mm; }
        .meta .u { display: inline-block; border-bottom: 1px solid #000; min-width: 34mm; text-align: center; font-weight: bold; }
        .hours { display: flex; font-size: 8pt; align-items: center; gap: 2mm; margin-bottom: 1.5mm; }
        .hours .brace { font-size: 18pt; line-height: .8; }
        .hours .lines div span { display: inline-block; border-bottom: 1px solid #000; min-width: 38mm; text-align: center; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #000; text-align: center; padding: 0 1px; height: 3.2mm; font-size: 8pt; line-height: 1; }
        th { font-weight: bold; font-size: 7.5pt; height: auto; padding: .6mm 0; }
        td.day { width: 9mm; }
        td.note { font-size: 7pt; letter-spacing: .4px; text-transform: uppercase; }
        tr.weekend td { background: #f3f4f6; }
        tr.total td { font-weight: bold; height: 5mm; }
        .cert { font-size: 7.5pt; text-indent: 8mm; text-align: justify; margin: 1.2mm 0 2.5mm; font-style: italic; }
        .sig { border-bottom: 1px solid #000; margin: 0 8mm; height: 5mm; text-align: center; font-weight: bold; text-transform: uppercase; font-size: 9pt; }
        .sig-caption { text-align: center; font-size: 7.5pt; margin-bottom: 3mm; }
        .verified { font-size: 8pt; font-style: italic; margin-bottom: 3mm; }
        .foot-row { display: flex; align-items: flex-end; gap: 3mm; }
        .foot-row .signs { flex: 1; }
        .foot-row .vs { margin: 0; flex-direction: column; align-items: center; text-align: center; gap: .5mm; width: 30mm; }
        .foot-row .vs-text b, .foot-row .vs-text > div:nth-child(2) { display: none; }
        .head-row { display: flex; align-items: center; justify-content: space-between; }
        .head-row .lh { margin: 0; justify-content: flex-end; text-align: right; gap: 2mm; }
        .head-row .lh-spacer { display: none; }
        .head-row .lh-compact .lh-logo { width: 9mm; height: 9mm; }
        .verify-note { font-size: 6pt; text-align: center; color: #333; }
        .footer-note { font-size: 6.5pt; color: #555; text-align: right; margin-top: 2mm; font-family: system-ui, sans-serif; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { margin: 0; box-shadow: none; width: auto; min-height: 0; }
            tr.weekend td { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
        @media screen and (max-width: 1100px) { .sheet { width: auto; flex-direction: column; margin: 12px; } .copy + .copy { border-left: 0; border-top: 1px dashed #999; } }
    </style>
</head>
<body>
<div class="toolbar">
    <button type="button" onclick="window.print()">Print</button>
    <a href="{{ $backUrl }}">Back</a>
    <span>{{ count($records) }} employee(s) · print <b>Landscape</b> on A4 or Legal at 100% · two copies per page</span>
</div>

@forelse ($records as $r)
    @php
        $e = $r['employee'];
        $h = $r['hours'];
        $regular = $r['hours_label'];
        $inCharge = $e->verifier_name ?: ($settings['default_supervisor'] ?? '');
    @endphp
    <div class="sheet">
        @foreach ([1, 2] as $copy)
        <div class="copy">
            <div class="head-row"><div class="form-no">Civil Service Form No. 48</div><x-print-header compact /></div>
            <div class="title">DAILY TIME RECORD</div>
            <div class="circle">-----o0o-----</div>
            <div class="name">{{ $e->display_name }}</div>
            <div class="caption">(Name)</div>
            <div class="meta">For the month of <span class="u">{{ strtoupper($r['month']->format('F Y')) }}</span></div>
            <div class="hours">
                <div>Official hours for arrival<br>and departure</div>
                <div class="brace">{</div>
                <div class="lines">
                    <div>Regular days <span>{{ $regular }}</span></div>
                    <div>Saturdays <span>{{ $settings['saturday_hours'] ?? '' }}</span></div>
                </div>
            </div>
            <table>
                <thead>
                    <tr><th rowspan="2" style="width:9mm">Day</th><th colspan="2">A.M.</th><th colspan="2">P.M.</th><th colspan="2">Undertime</th></tr>
                    <tr><th>Arrival</th><th>Departure</th><th>Arrival</th><th>Departure</th><th>Hours</th><th>Minutes</th></tr>
                </thead>
                <tbody>
                @foreach ($r['rows'] as $row)
                    <tr @class(['weekend' => $row['date'] && ! $row['is_workday']])>
                        <td class="day">{{ $row['date'] ? $row['day'] : '' }}</td>
                        @if ($row['note'])
                            <td colspan="4" class="note">{{ $row['note'] }}</td>
                        @else
                            <td>{{ $row['am_in'] }}</td><td>{{ $row['am_out'] }}</td><td>{{ $row['pm_in'] }}</td><td>{{ $row['pm_out'] }}</td>
                        @endif
                        <td>{{ $row['ut_hours'] }}</td><td>{{ $row['ut_minutes'] }}</td>
                    </tr>
                @endforeach
                    <tr class="total"><td colspan="5" style="text-align:right;padding-right:3mm">TOTAL</td>
                        <td>{{ $r['total_minutes'] ? $r['total_hours'] : '' }}</td><td>{{ $r['total_minutes'] ? $r['total_rem_minutes'] : '' }}</td></tr>
                </tbody>
            </table>
            <p class="cert">I certify on my honor that the above is a true and correct report of the hours of work performed, record of which was made daily at the time of arrival and departure from office.</p>
            <div class="foot-row">
            <div class="signs">
            <div class="sig" style="margin-top:1.5mm"></div>
            <div class="sig-caption">(Signature)</div>
            <div class="verified">VERIFIED as to the prescribed office hours:</div>
            <div class="sig">{{ $inCharge }}</div>
            <div class="sig-caption" style="margin-bottom:0">In Charge</div>
            </div>
            @if ($copy === 1)
                @php
                    $stamp = \Illuminate\Support\Facades\Blade::render('<x-verify-stamp compact type="form48" :key="$key" :employee="$e" :title="$title" :summary="$summary" />', [
                        'key' => 'form48:'.$e->id.':'.$r['month']->format('Y-m'), 'e' => $e, 'title' => 'DTR '.$r['month']->format('F Y').' — '.$e->full_name,
                        'summary' => ['Employee' => $e->full_name, 'Month' => $r['month']->format('F Y'), 'Days present' => $r['days_present'], 'Absences' => $r['days_absent'],
                            'Leave days' => $r['leave_days'] + 0, 'Total undertime' => $r['total_hours'].' h '.$r['total_rem_minutes'].' min', 'Official hours' => $r['hours_label']],
                    ]);
                @endphp
            @endif
            {!! $stamp !!}
            </div>
        </div>
        @endforeach
    </div>
@empty
    <div class="sheet" style="padding:20mm;font-family:system-ui">No employees matched.</div>
@endforelse
</body>
</html>
