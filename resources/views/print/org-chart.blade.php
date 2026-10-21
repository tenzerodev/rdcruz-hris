@extends('print._layout')
@section('title', 'Organizational chart — '.$s->name)
@section('page-size', 'A3 landscape')
@section('content')
<style>
    .page { width: auto; max-width: 420mm; }
    .chart { display: flex; justify-content: center; }
    .chart ul { display: flex; justify-content: center; padding-top: 5mm; position: relative; margin: 0; padding-left: 0; }
    .chart li { list-style: none; text-align: center; position: relative; padding: 5mm 1.5mm 0; }
    .chart li::before, .chart li::after { content: ''; position: absolute; top: 0; right: 50%; border-top: 1px solid #555; width: 50%; height: 5mm; }
    .chart li::after { right: auto; left: 50%; border-left: 1px solid #555; }
    .chart li:only-child::after, .chart li:only-child::before { display: none; }
    .chart li:only-child { padding-top: 0; }
    .chart li:first-child::before, .chart li:last-child::after { border: 0 none; }
    .chart li:last-child::before { border-right: 1px solid #555; }
    .chart ul ul::before { content: ''; position: absolute; top: 0; left: 50%; border-left: 1px solid #555; height: 5mm; }
    .chart > ul { padding-top: 0; }
    .ou { display: inline-block; border: 1px solid #333; border-radius: 2mm; padding: 1.5mm 2.5mm; min-width: 30mm; max-width: 52mm; font-size: 7.5pt; background: #fff; }
    .ou .lv { font-size: 6pt; text-transform: uppercase; letter-spacing: .4px; color: #555; }
    .ou .nm { font-weight: bold; }
    .ou .hd { margin-top: .8mm; } .ou .ct { color: #555; font-size: 6.5pt; }
    .lv-office > .ou { background: #1f3b6e; color: #fff; } .lv-office > .ou .lv, .lv-office > .ou .ct { color: #dbe4f5; }
    .lv-department > .ou { background: #e7eefb; }
    .lv-division > .ou { background: #f4f6fa; }
    @media print { .ou, .lv-office > .ou { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
</style>
@php
    $render = function ($nodes) use (&$render) {
        $html = '<ul>';
        foreach ($nodes as $n) {
            $u = $n['unit'];
            $html .= '<li class="lv-'.e($u->level).'"><div class="ou"><div class="lv">'.e($u->level_label).'</div><div class="nm">'.e($u->name).'</div>'
                .'<div class="hd">'.($u->head ? e($u->head->display_name).($u->head_title ? '<br><span class="ct">'.e($u->head_title).'</span>' : '') : '<span class="ct">Vacant</span>').'</div>'
                .'<div class="ct">'.$u->assignments_count.' personnel</div></div>';
            if ($n['children']) {
                $html .= $render($n['children']);
            }
            $html .= '</li>';
        }

        return $html.'</ul>';
    };
@endphp
<x-print-header />
<h1>ORGANIZATIONAL CHART</h1>
<p class="c small">{{ $s->name }} · {{ $s->kind_label }}{{ $s->effective_from ? ' · effective '.$s->effective_from->format('F j, Y') : '' }}{{ $s->effective_to ? ' to '.$s->effective_to->format('F j, Y') : '' }}{{ $s->reference ? ' · '.$s->reference : '' }}</p>
<div class="chart">{!! $tree ? $render($tree) : '<p>No units.</p>' !!}</div>
<x-verify-stamp type="org_chart" :key="'org:'.$s->id" :title="'Organizational chart — '.$s->name" :summary="[
    'Structure' => $s->name, 'Kind' => $s->kind_label, 'Status' => \App\Models\OrgStructure::STATUSES[$s->status],
    'Effective' => ($s->effective_from?->format('M j, Y') ?? '—').($s->effective_to ? ' – '.$s->effective_to->format('M j, Y') : ''),
    'Units' => $s->units()->count(), 'Reference' => $s->reference,
]" style="margin-top:8mm" />
@endsection
