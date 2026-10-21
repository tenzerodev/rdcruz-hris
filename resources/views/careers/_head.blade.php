@php $logo = \App\Models\Setting::logoUrl(); @endphp
<div class="page-head" style="margin-top:12px">
    <a class="brand" href="{{ route('careers.index') }}" style="text-decoration:none;color:inherit"><span class="logo" aria-hidden="true">@if ($logo)<img src="{{ $logo }}" alt="">@else HR @endif</span><span><b>Careers</b><small>{{ \App\Models\Setting::get('agency_name') }}</small></span></a>
    <div class="actions"><a class="btn btn-sm" href="{{ route('careers.index') }}">Open positions</a><a class="btn btn-sm" href="{{ route('careers.status') }}">Check my application</a></div>
</div>
