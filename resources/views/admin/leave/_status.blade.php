@php $tone = ['pending' => 'badge-warn', 'recommended' => 'badge-info', 'approved' => 'badge-ok', 'disapproved' => 'badge-error', 'cancelled' => ''][$status] ?? ''; @endphp
<span class="badge {{ $tone }}">{{ ucfirst($status) }}</span>
