@if (session('status'))<div class="alert alert-ok" role="status"><x-icon name="check" />{{ session('status') }}</div>@endif
@if (session('warning'))<div class="alert alert-warn" role="status"><x-icon name="alert" />{{ session('warning') }}</div>@endif
@if (! empty($readOnly) && auth()->check())<div class="alert alert-warn" role="note"><x-icon name="shield" />Read-only: your role ({{ auth()->user()->roleLabel() }}) can view this page but not change it.</div>@endif
@if ($errors->any())
    <div class="alert alert-error" role="alert"><x-icon name="alert" /><div>@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div></div>
@endif
