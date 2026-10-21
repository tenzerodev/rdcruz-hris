@props(['key'])
@if (auth()->user()?->canManage('imports'))
<a {{ $attributes->merge(['class' => 'btn']) }} href="{{ route('admin.imports.show', $key) }}"><x-icon name="list" size="16" /> Import CSV</a>
@endif
