@php $u = $node['unit']; @endphp
<li>
    <a href="{{ route('admin.org.show', [$s, 'unit' => $u->id]) }}" @class(['org-node', 'lvl-'.$u->level, 'sel' => $edit?->id === $u->id])>
        <span class="badge">{{ $u->level_label }}</span>
        <span class="org-name">{{ $u->name }}{{ $u->code ? ' ('.$u->code.')' : '' }}</span>
        <span class="org-meta">{{ $u->head ? ($u->head_title ? $u->head_title.': ' : '').$u->head->display_name : 'No head' }} · {{ $u->assignments_count }}</span>
    </a>
    @if ($node['children'])
        <ul>@foreach ($node['children'] as $child)@include('admin.org._node', ['node' => $child])@endforeach</ul>
    @endif
</li>
