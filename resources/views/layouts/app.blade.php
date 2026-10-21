<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="face-models" content="{{ asset('vendor/face-api/models') }}">
    <title>@yield('title', 'HRIS') · {{ config('app.name') }}</title>
    {{-- Apply the saved theme before the page paints (light / dark / system). --}}
    <script>
        (function () {
            try { var t = localStorage.getItem('hris-theme'); if (t === 'light' || t === 'dark') document.documentElement.dataset.theme = t; } catch (e) {}
        })();
    </script>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=9">
    <link rel="manifest" href="{{ route('pwa.manifest') }}">
    <meta name="theme-color" content="#1d4ed8">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="HRIS">
    <link rel="apple-touch-icon" href="{{ route('pwa.icon', 180) }}">
    @stack('head')
</head>
<body>
@php
    $bare = View::hasSection('bare') || ! auth()->check();
@endphp
@if ($bare)
    <main class="@yield('main-class', 'container')">
        @include('layouts._flash')
        @yield('content')
    </main>
@else
@php
    $user = auth()->user();
    $c = $navCounts ?? [];
    $mine = [
        null => [
            ['me.index', 'Home', 'home', 'me.index'],
            ['me.punch', 'Time in / out', 'camera', 'me.punch'],
            ['me.memos', 'Memos', 'megaphone', 'me.memos*', $c['memos'] ?? 0],
        ],
        'Requests' => [
            ['me.leave', 'Leave', 'calendar', 'me.leave'],
            ['me.overtime', 'Overtime', 'timer', 'me.overtime'],
            ['me.travel', 'Travel / OB', 'pin', 'me.travel'],
            ['me.pass-slips', 'Pass slips', 'logout', 'me.pass-slips*'],
            ['me.wfh', 'Work from home', 'laptop', 'me.wfh*'],
        ],
        'My records' => [
            ['me.payslips', 'Payslips', 'wallet', 'me.payslips*'],
            ['me.ipcr', 'Performance (IPCR)', 'star', 'me.ipcr*'],
            ['me.learning', 'Training', 'book', 'me.learning*'],
            ['me.profile', 'My 201 / PDS', 'idcard', 'me.profile*'],
            ['me.devices', 'My devices', 'monitor', 'me.devices*'],
            ['me.privacy', 'My data & privacy', 'shield', 'me.privacy*'],
        ],
    ];
    if ($user->employee && $user->isSupervisor()) {
        $mine['My team'] = [['me.approvals', 'Approvals', 'inbox', 'me.approvals', $c['approvals'] ?? 0]];
    }
    // Staff use the admin side, and switch to their own employee pages when they have an employee record.
    $onMine = ! $user->isStaff() || (request()->routeIs('me.*') && $user->employee);
    if ($onMine) {
        $groups = $mine;
        if ($user->isStaff()) {
            $groups[null][] = ['admin.dashboard', 'Back to HR admin', 'arrow', 'none'];
        }
    } else {
        // Each item: route, label, icon, active pattern, badge count, permission area.
        $staff = [
            null => [
                ['admin.dashboard', 'Dashboard', 'home', 'admin.dashboard', 0, null],
            ],
            'People' => [
                ['admin.employees.index', 'Employees', 'users', 'admin.employees.*', 0, 'people'],
                ['admin.org.index', 'Organization', 'chart', 'admin.org.*', 0, 'people'],
                ['admin.positions.index', 'Plantilla', 'briefcase', 'admin.positions.*', 0, 'people'],
                ['admin.recruitment.index', 'Recruitment', 'search', 'admin.recruitment.*', 0, 'recruitment'],
                ['admin.compensation.index', 'Step increments & salary', 'chart', 'admin.compensation.*', 0, 'people'],
                ['admin.clearances.index', 'Clearance & exit', 'logout', 'admin.clearances.*', 0, 'people'],
                ['admin.announcements.index', 'Memos & announcements', 'megaphone', 'admin.announcements.*', 0, 'announcements'],
                ['admin.cases.index', 'Administrative cases', 'scale', 'admin.cases.*', 0, 'cases'],
                ['admin.reports.workforce', 'Workforce report', 'chart', 'admin.reports.workforce', 0, 'reports'],
                ['admin.reports.analytics', 'HR analytics', 'chart', 'admin.reports.analytics', 0, 'reports'],
            ],
            'Time & attendance' => [
                ['admin.punches.index', 'Attendance log', 'clock', 'admin.punches.*', 0, 'attendance'],
                ['admin.dtr.index', 'DTR (Form 48)', 'file', 'admin.dtr.*', 0, 'attendance'],
                ['admin.reports.habitual', 'Tardiness & absences', 'alert', 'admin.reports.habitual', 0, 'reports'],
                ['admin.overtime.index', 'Overtime', 'timer', 'admin.overtime.*', $c['overtime'] ?? 0, 'overtime'],
                ['admin.travel.index', 'Travel / OB', 'pin', 'admin.travel.*', $c['travel'] ?? 0, 'travel'],
                ['admin.pass-slips.index', 'Pass slips', 'logout', 'admin.pass-slips.*', $c['pass_slips'] ?? 0, 'attendance'],
                ['admin.wfh.index', 'Work from home', 'laptop', 'admin.wfh.*', 0, 'attendance'],
                ['admin.devices.index', 'Issued computers', 'monitor', 'admin.devices.*', 0, 'attendance'],
            ],
            'Leave' => [
                ['admin.leave.index', 'Leave applications', 'calendar', ['admin.leave.index'], $c['leave'] ?? 0, 'leave'],
                ['admin.leave.posting', 'Leave card posting', 'list', 'admin.leave.posting', 0, 'leave'],
                ['admin.leave.monetization', 'Monetization & terminal leave', 'wallet', 'admin.leave.monetization*', 0, 'leave'],
            ],
            'Performance & pay' => [
                ['admin.ipcr.index', 'IPCR', 'star', 'admin.ipcr.*', 0, 'performance'],
                ['admin.office-ratings.index', 'OPCR / DPCR', 'target', ['admin.office-ratings.*', 'performance.*'], 0, 'performance'],
                ['admin.learning.index', 'Training & development', 'book', 'admin.learning.*', 0, 'learning'],
                ['admin.payroll.runs.index', 'Payroll & payslips', 'wallet', ['admin.payroll.runs.*', 'admin.payslips.*'], 0, 'payroll'],
                ['admin.payroll.index', 'Payroll inputs', 'list', ['admin.payroll.index', 'admin.payroll.export'], 0, 'payroll'],
                ['admin.loans.index', 'Loans & deductions', 'list', 'admin.loans.*', 0, 'payroll'],
                ['admin.remittances.index', 'Remittances', 'inbox', 'admin.remittances.*', 0, 'payroll'],
                ['admin.tax.index', 'Annual tax (2316)', 'file', 'admin.tax.*', 0, 'payroll'],
            ],
            'CSC reports' => [
                ['admin.reports.plantilla', 'Plantilla of Personnel', 'briefcase', 'admin.reports.plantilla', 0, 'reports'],
                ['admin.reports.rai', 'Appointments issued (RAI)', 'file', 'admin.reports.rai', 0, 'reports'],
                ['admin.reports.loyalty', 'Loyalty awards', 'star', 'admin.reports.loyalty', 0, 'reports'],
                ['admin.reports.saln', 'SALN filing', 'shield', 'admin.reports.saln', 0, 'reports'],
            ],
            'Setup' => [
                ['admin.settings.edit', 'HR settings', 'settings', 'admin.settings.*', 0, 'settings'],
                ['admin.holidays.index', 'Holidays', 'flag', 'admin.holidays.*', 0, 'attendance'],
                ['admin.kiosks.index', 'Kiosks', 'monitor', 'admin.kiosks.*', 0, 'attendance'],
                ['admin.imports.index', 'Import data (CSV)', 'list', 'admin.imports.*', 0, 'imports'],
                ['admin.documents.index', 'Issued documents', 'check', 'admin.documents.*', 0, 'documents'],
                ['admin.audit', 'Audit log', 'shield', 'admin.audit', 0, 'audit'],
            ],
            'System' => [
                ['admin.users.index', 'User accounts', 'users', 'admin.users.*', 0, 'system'],
                ['admin.backups.index', 'Backups', 'inbox', 'admin.backups.*', 0, 'system'],
                ['admin.system.index', 'System status', 'monitor', 'admin.system.*', 0, 'system'],
            ],
        ];
        $groups = [];
        foreach ($staff as $label => $items) {
            $items = array_values(array_filter($items, fn ($i) => $i[5] === null || $user->canSee($i[5])));
            if ($items) {
                $groups[$label] = $items;
            }
        }
        if ($user->employee) {
            $groups['Me'] = [['me.index', 'My time & requests', 'user', 'none', $c['approvals'] ?? 0]];
        }
    }
@endphp
<div class="app" data-nav>
    <aside class="sidebar" id="sidebar" aria-label="Main navigation">
        <div class="sidebar-head">
            @php $logo = \App\Models\Setting::logoUrl(); @endphp
            <a class="brand" href="{{ url('/') }}"><span class="logo" aria-hidden="true">@if ($logo)<img src="{{ $logo }}" alt="">@else HR @endif</span><span><b>HRIS</b><small>{{ \App\Models\Setting::get('agency_name') }}</small></span></a>
            <button class="icon-btn only-mobile" type="button" data-nav-close aria-label="Close menu"><x-icon name="x" /></button>
        </div>
        <nav class="side-nav">
            @foreach ($groups as $label => $items)
                <div class="nav-group">
                    @if ($label)<div class="nav-label">{{ $label }}</div>@endif
                    @foreach ($items as $item)
                        @php
                            [$route, $text, $icon, $pattern] = $item;
                            $active = request()->routeIs(...(array) $pattern);
                        @endphp
                        <a href="{{ route($route) }}" @class(['nav-item', 'active' => $active]) @if ($active) aria-current="page" @endif>
                            <x-icon :name="$icon" />
                            <span>{{ $text }}</span>
                            @if (($item[4] ?? 0) > 0)<span class="count" title="Waiting for action">{{ $item[4] }}</span>@endif
                        </a>
                    @endforeach
                </div>
            @endforeach
        </nav>
        <div class="sidebar-foot">
            @php $unread = $c['unread'] ?? 0; @endphp
            <a href="{{ route('notifications') }}" @class(['nav-item', 'active' => request()->routeIs('notifications*')])><x-icon name="bell" /><span>Notifications</span>@if ($unread)<span class="count" title="Unread">{{ $unread > 99 ? '99+' : $unread }}</span>@endif</a>
            <a href="{{ route('help') }}" @class(['nav-item', 'active' => request()->routeIs('help')])><x-icon name="info" /><span>Help</span></a>
            <div class="theme-switch" role="radiogroup" aria-label="Appearance">
                <button type="button" role="radio" data-theme-set="light" title="Light"><x-icon name="sun" size="16" /><span>Light</span></button>
                <button type="button" role="radio" data-theme-set="dark" title="Dark"><x-icon name="moon" size="16" /><span>Dark</span></button>
                <button type="button" role="radio" data-theme-set="system" title="Match device"><x-icon name="laptop" size="16" /><span>Auto</span></button>
            </div>
            <div class="me-card">
                <span class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                <span class="me-name"><b>{{ $user->name }}</b><small>{{ $user->isStaff() ? $user->roleLabel() : ($user->employee?->position ?: 'Employee') }}</small></span>
                <a class="icon-btn" href="{{ route('account.security') }}" title="Sign-in & security" aria-label="Sign-in and security"><x-icon name="settings" /></a>
                <form method="post" action="{{ route('logout') }}">@csrf<button class="icon-btn" title="Log out" aria-label="Log out"><x-icon name="logout" /></button></form>
            </div>
        </div>
    </aside>
    <div class="scrim" data-nav-close></div>
    <div class="main-col">
        <header class="mobile-bar">
            <button class="icon-btn" type="button" data-nav-open aria-label="Open menu" aria-controls="sidebar"><x-icon name="menu" /></button>
            <a class="brand" href="{{ url('/') }}"><span class="logo" aria-hidden="true">@if ($logo)<img src="{{ $logo }}" alt="">@else HR @endif</span><b>HRIS</b></a>
            <a class="icon-btn bell" href="{{ route('notifications') }}" aria-label="Notifications{{ ($c['unread'] ?? 0) ? ', '.$c['unread'].' unread' : '' }}"><x-icon name="bell" />@if ($c['unread'] ?? 0)<span class="dot"></span>@endif</a>
        </header>
        <main class="@yield('main-class', 'container')">
            @include('layouts._flash')
            @yield('content')
        </main>
    </div>
</div>
@endif
<script>
(function () {
  var root = document.documentElement;
  function current() { try { return localStorage.getItem('hris-theme') || 'system'; } catch (e) { return 'system'; } }
  function apply(t) {
    if (t === 'light' || t === 'dark') root.dataset.theme = t; else delete root.dataset.theme;
    document.querySelectorAll('[data-theme-set]').forEach(function (b) { b.setAttribute('aria-checked', b.dataset.themeSet === t ? 'true' : 'false'); });
  }
  document.querySelectorAll('[data-theme-set]').forEach(function (b) {
    b.addEventListener('click', function () { try { localStorage.setItem('hris-theme', b.dataset.themeSet); } catch (e) {} apply(b.dataset.themeSet); });
  });
  apply(current());
  if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1')) {
    window.addEventListener('load', function () { navigator.serviceWorker.register('/sw.js').catch(function () {}); });
  }
  // "Install app" buttons appear when the browser can install the HRIS.
  var installEvent = null;
  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault(); installEvent = e;
    document.querySelectorAll('[data-install]').forEach(function (b) { b.hidden = false; });
  });
  document.querySelectorAll('[data-install]').forEach(function (b) {
    b.addEventListener('click', function () { if (installEvent) { installEvent.prompt(); installEvent = null; b.hidden = true; } });
  });
  var standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone;
  var ios = /iphone|ipad|ipod/i.test(navigator.userAgent);
  document.querySelectorAll('[data-install-ios]').forEach(function (el) { el.hidden = !(ios && !standalone); });
  var app = document.querySelector('[data-nav]');
  if (app) {
    document.querySelectorAll('[data-nav-open]').forEach(function (b) { b.addEventListener('click', function () { app.classList.add('nav-open'); }); });
    document.querySelectorAll('[data-nav-close]').forEach(function (b) { b.addEventListener('click', function () { app.classList.remove('nav-open'); }); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') app.classList.remove('nav-open'); });
  }
})();
</script>
@stack('scripts')
</body>
</html>
