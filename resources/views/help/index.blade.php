@extends('layouts.app')
@section('title', 'Help')
@push('head')
<style>
    .help { display: grid; grid-template-columns: 240px minmax(0, 1fr); gap: 24px; align-items: start; }
    .help-toc { position: sticky; top: 16px; max-height: calc(100vh - 32px); overflow: auto; }
    .help-toc h3 { font-size: .75rem; text-transform: uppercase; letter-spacing: .06em; color: var(--muted); margin: 14px 0 6px; }
    .help-toc a { display: block; padding: 4px 8px; border-radius: 6px; color: var(--text); text-decoration: none; font-size: .88rem; }
    .help-toc a:hover { background: var(--surface-2); }
    .topic { scroll-margin-top: 16px; margin-bottom: 16px; }
    .topic h2 { margin-top: 0; }
    .topic ol { padding-left: 22px; line-height: 1.65; }
    .topic .shot { display: block; margin-top: 12px; border: 1px solid var(--border); border-radius: 10px; overflow: hidden; max-width: 900px; }
    .topic .shot.narrow { max-width: 380px; }
    .topic .shot img { display: block; width: 100%; height: auto; }
    .topic .tips { background: var(--surface-2); border-radius: 8px; padding: 10px 14px; font-size: .88rem; margin-top: 10px; }
    .topic .tips p { margin: 2px 0; }
    .group-title { margin: 22px 0 10px; font-size: 1.05rem; color: var(--muted); }
    @media (max-width: 900px) { .help { grid-template-columns: 1fr; } .help-toc { position: static; max-height: none; } }
    @media print { .help { display: block; } .help-toc, .help-search { display: none; } .topic { break-inside: avoid; } }
</style>
@endpush
@section('content')
<div class="page-head">
    <div><h1>Help</h1><div class="muted">Step-by-step instructions with screenshots. {{ $all ? 'Showing every topic.' : 'Showing the topics for your role.' }}
        @if ($hidden > 0 && ! $all)<a href="{{ route('help', ['all' => 1]) }}">Show all topics</a>@elseif ($all)<a href="{{ route('help') }}">Only my topics</a>@endif</div></div>
    <button class="btn" type="button" onclick="window.print()">Print</button>
</div>
<div class="help">
    <nav class="card help-toc" aria-label="Help topics">
        <div class="field help-search" style="margin:0"><label for="help-q" class="sr">Search help</label><input type="search" id="help-q" placeholder="Search help…" autocomplete="off"></div>
        @foreach (\App\Support\HelpTopics::GROUPS as $g => $label)
            @continue(! isset($groups[$g]))
            <h3>{{ $label }}</h3>
            @foreach ($groups[$g] as $t)<a href="#{{ $t['id'] }}" data-toc="{{ $t['id'] }}">{{ $t['title'] }}</a>@endforeach
        @endforeach
    </nav>
    <div>
        @foreach (\App\Support\HelpTopics::GROUPS as $g => $label)
            @continue(! isset($groups[$g]))
            <h2 class="group-title" data-group>{{ $label }}</h2>
            @foreach ($groups[$g] as $t)
                <section class="card topic" id="{{ $t['id'] }}" data-topic data-text="{{ mb_strtolower($t['title'].' '.$t['summary'].' '.implode(' ', $t['steps']).' '.implode(' ', $t['tips'] ?? [])) }}">
                    <h2>{{ $t['title'] }}</h2>
                    <p class="muted">{{ $t['summary'] }}</p>
                    <ol>@foreach ($t['steps'] as $step)<li>{!! \App\Support\HelpTopics::format($step) !!}</li>@endforeach</ol>
                    @if (! empty($t['tips']))<div class="tips">@foreach ($t['tips'] as $tip)<p>💡 {!! \App\Support\HelpTopics::format($tip) !!}</p>@endforeach</div>@endif
                    @if ($t['has_image'])
                        <a @class(['shot', 'narrow' => $t['narrow']]) href="{{ asset('images/help/'.$t['image']) }}" target="_blank" title="Open full size"><img src="{{ asset('images/help/'.$t['image']) }}" alt="Screenshot: {{ $t['title'] }}" loading="lazy"></a>
                    @endif
                    @if (! empty($t['link']) && \Illuminate\Support\Facades\Route::has($t['link'][0]) && $t['mine'])
                        <p style="margin:12px 0 0"><a class="btn btn-sm" href="{{ route($t['link'][0]) }}">{{ $t['link'][1] }} <x-icon name="arrow" size="14" /></a></p>
                    @endif
                </section>
            @endforeach
        @endforeach
        <p class="muted" data-none hidden>No help topics match your search.</p>
        <p class="small muted">Still stuck? Contact your HR office{{ \App\Models\Setting::get('hr_email') ? ' at '.\App\Models\Setting::get('hr_email') : '' }}.</p>
    </div>
</div>
<script>
(function () {
  var q = document.getElementById('help-q');
  q.addEventListener('input', function () {
    var term = q.value.trim().toLowerCase(), any = false;
    document.querySelectorAll('[data-topic]').forEach(function (t) {
      var show = !term || t.dataset.text.indexOf(term) !== -1;
      t.hidden = !show; any = any || show;
      var link = document.querySelector('[data-toc="' + t.id + '"]'); if (link) link.hidden = !show;
    });
    document.querySelector('[data-none]').hidden = any;
  });
})();
</script>
@endsection
