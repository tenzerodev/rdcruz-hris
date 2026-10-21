@php $tone = ['open' => 'badge-ok', 'draft' => 'badge-warn', 'closed' => 'badge-info', 'filled' => 'badge-ok', 'received' => 'badge-warn', 'qualified' => 'badge-info',
    'shortlisted' => 'badge-info', 'selected' => 'badge-ok', 'appointed' => 'badge-ok', 'not_qualified' => 'badge-error', 'not_selected' => ''][$status] ?? ''; @endphp
<span class="badge {{ $tone }}">{{ $label }}</span>
