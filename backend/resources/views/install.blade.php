<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Install</title>
    {{-- Inline styles: the built front-end assets may not be there yet on a fresh upload. --}}
    <style>
        body { margin: 0; font: 15px/1.5 system-ui, sans-serif; background: #f4f4f5; color: #18181b; }
        main { max-width: 640px; margin: 0 auto; padding: 24px 16px; }
        h1 { font-size: 24px; margin: 0 0 4px; }
        h2 { font-size: 16px; margin: 24px 0 8px; }
        .card { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        label { display: block; font-weight: 600; margin: 12px 0 4px; }
        input[type=text], input[type=email], input[type=url], input[type=number], input[type=password] { width: 100%; box-sizing: border-box; padding: 10px; border: 1px solid #d4d4d8; border-radius: 8px; font: inherit; }
        .check { display: flex; gap: 8px; align-items: center; font-weight: 400; }
        .row { display: grid; grid-template-columns: 3fr 1fr; gap: 12px; }
        ul.req { list-style: none; padding: 0; margin: 0; columns: 2; }
        .ok { color: #047857; } .bad { color: #b91c1c; font-weight: 600; }
        .errors { background: #fef2f2; color: #991b1b; border-radius: 8px; padding: 12px 16px; }
        .hint { color: #52525b; font-size: 13px; }
        button { margin-top: 20px; width: 100%; padding: 12px; border: 0; border-radius: 8px; background: #4f46e5; color: #fff; font: 600 16px system-ui; cursor: pointer; }
        button:disabled { background: #a1a1aa; }
    </style>
</head>
<body>
<main>
    <div class="card">
        <h1>Install the rental platform</h1>
        <p class="hint">Create an empty MySQL 8 database first. This takes about a minute; don't close the page.</p>

        @if ($problems)
            <div class="errors"><ul>@foreach ($problems as $problem)<li>{{ $problem }}</li>@endforeach</ul></div>
        @endif

        <h2>Server requirements</h2>
        <ul class="req">
            @foreach ($requirements as $label => $met)
                <li class="{{ $met ? 'ok' : 'bad' }}">{{ $met ? '✓' : '✗' }} {{ $label }}</li>
            @endforeach
        </ul>
        @php($ready = ! in_array(false, $requirements, true))

        <form method="post" action="/install" onsubmit="this.querySelector('button').disabled = true">
            <h2>Site</h2>
            <label for="app_name">Site name</label>
            <input id="app_name" name="app_name" type="text" required value="{{ $old['app_name'] ?? 'Rental Platform' }}">
            <label for="app_url">Site address</label>
            <input id="app_url" name="app_url" type="url" required value="{{ $old['app_url'] ?? $url }}">

            <h2>Database</h2>
            <div class="row">
                <div><label for="db_host">Host</label><input id="db_host" name="db_host" type="text" required value="{{ $old['db_host'] ?? '127.0.0.1' }}"></div>
                <div><label for="db_port">Port</label><input id="db_port" name="db_port" type="number" required value="{{ $old['db_port'] ?? 3306 }}"></div>
            </div>
            <label for="db_database">Database name</label>
            <input id="db_database" name="db_database" type="text" required value="{{ $old['db_database'] ?? '' }}">
            <label for="db_username">User</label>
            <input id="db_username" name="db_username" type="text" required value="{{ $old['db_username'] ?? '' }}" autocomplete="off">
            <label for="db_password">Password</label>
            <input id="db_password" name="db_password" type="password" autocomplete="new-password">
            <label class="check"><input type="checkbox" name="redis" value="1" @checked($old['redis'] ?? false)> Redis is installed (faster cache and queue; leave off on shared hosting)</label>

            <h2>Administrator</h2>
            <label for="admin_name">Name</label>
            <input id="admin_name" name="admin_name" type="text" required value="{{ $old['admin_name'] ?? '' }}">
            <label for="admin_email">Email</label>
            <input id="admin_email" name="admin_email" type="email" required value="{{ $old['admin_email'] ?? '' }}">
            <label for="admin_password">Password (at least 8 characters)</label>
            <input id="admin_password" name="admin_password" type="password" required minlength="8" autocomplete="new-password">
            <label for="admin_password_confirmation">Password again</label>
            <input id="admin_password_confirmation" name="admin_password_confirmation" type="password" required autocomplete="new-password">

            <label class="check"><input type="checkbox" name="demo" value="1" @checked($old['demo'] ?? true)> Add the demo fleet (Mumbai branch, 28 vehicles with photos)</label>

            <button type="submit" @disabled(! $ready)>{{ $ready ? 'Install' : 'Fix the requirements above first' }}</button>
        </form>
    </div>
</main>
</body>
</html>
