@extends('layouts.app')
@section('title', 'Backups')
@section('content')
<div class="page-head">
    <div><h1>Backups</h1><div class="muted">A backup holds all records and uploaded files (logo, 201 documents). Keep copies off this server — a USB drive in a safe, or the agency's file server.</div></div>
    <form method="post" action="{{ route('admin.backups.store') }}">@csrf<button class="btn btn-primary">Make a backup now</button></form>
</div>

<div class="grid grid-2" style="margin-bottom:16px">
    <section class="card">
        <h2>Automatic backups</h2>
        <form method="post" action="{{ route('admin.system.backups') }}">
            @csrf @method('put')
            <label class="check"><input type="checkbox" name="backup_enabled" value="1" @checked($s['backup_enabled'] === '1')> <span>Make a backup every night at 1:00 AM</span></label>
            <label class="check"><input type="checkbox" name="backup_include_snapshots" value="1" @checked($s['backup_include_snapshots'] === '1')> <span>Include punch photos (makes backups much larger)</span></label>
            <div class="field"><label for="backup_keep">Keep the newest</label><input type="number" id="backup_keep" name="backup_keep" min="1" max="365" value="{{ $s['backup_keep'] }}" required style="width:110px"> <span class="small muted">backups on this server</span></div>
            <button class="btn btn-primary">Save</button>
        </form>
        <p class="hint">Nightly backups need the scheduled task (cron) to be set up — see System status.</p>
    </section>
    <section class="card">
        <h2>Restore</h2>
        <div class="alert alert-warn"><x-icon name="alert" /><div><b>Restoring replaces all current data</b> with the backup's. A safety backup of the current data is made first. Everyone is signed out.</div></div>
        <form method="post" action="{{ route('admin.backups.restore') }}" enctype="multipart/form-data">
            @csrf
            <div class="field"><label for="name">Restore from</label><select id="name" name="name" data-restore-source>
                <option value="">— Upload a backup file below —</option>
                @foreach ($backups as $b)<option value="{{ $b['name'] }}">{{ $b['date']->format('M j, Y g:i A') }} · {{ $b['name'] }}</option>@endforeach
            </select></div>
            <div class="field" data-upload><label for="file">Backup file (.zip)</label><input type="file" id="file" name="file" accept=".zip,application/zip"></div>
            <div class="row">
                <div class="field"><label for="confirm">Type RESTORE to confirm</label><input type="text" id="confirm" name="confirm" autocomplete="off" required pattern="RESTORE"></div>
                <div class="field"><label for="password">Your password</label><input type="password" id="password" name="password" autocomplete="current-password" required></div>
            </div>
            <button class="btn btn-danger">Restore</button>
        </form>
    </section>
</div>

<section class="card">
    <h2>Backups on this server</h2>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Made</th><th>File</th><th class="num">Size</th><th></th></tr></thead>
        <tbody>
        @forelse ($backups as $b)
            <tr>
                <td>{{ $b['date']->format('M j, Y g:i A') }}<div class="small muted">{{ $b['date']->diffForHumans() }}</div></td>
                <td><code>{{ $b['name'] }}</code></td>
                <td class="num">{{ number_format($b['size'] / 1024 / 1024, 1) }} MB</td>
                <td><div class="actions">
                    <a class="btn btn-sm" href="{{ route('admin.backups.download', $b['name']) }}">Download</a>
                    <form method="post" action="{{ route('admin.backups.destroy', $b['name']) }}" onsubmit="return confirm('Delete this backup?')">@csrf @method('delete')<button class="btn btn-sm btn-danger">Delete</button></form>
                </div></td>
            </tr>
        @empty <tr><td colspan="4" class="empty">No backups yet. Make one now.</td></tr> @endforelse
        </tbody>
    </table></div>
    <p class="hint">Backups contain personal data. Store copies where only authorized staff can reach them. They can only be restored on a server with the same APP_KEY — keep a copy of the .env file with the backups.</p>
</section>
<script>
(function () {
  var sel = document.querySelector('[data-restore-source]'), up = document.querySelector('[data-upload]');
  function sync() { up.hidden = sel.value !== ''; }
  sel.addEventListener('change', sync); sync();
})();
</script>
@endsection
