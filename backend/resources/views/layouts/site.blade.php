<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-zinc-50 font-sans text-zinc-900 antialiased">
    <header class="border-b border-zinc-200 bg-white">
        <nav class="mx-auto flex max-w-6xl flex-wrap items-center gap-x-6 gap-y-2 px-4 py-3">
            <a href="{{ route('home') }}" class="text-lg font-bold tracking-tight">{{ config('app.name') }}</a>
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
                <a href="{{ route('home') }}" class="hover:underline">{{ __('site.nav.search') }}</a>
                @auth
                    <a href="{{ route('account.bookings') }}" class="hover:underline">{{ __('site.nav.my_bookings') }}</a>
                    <a href="{{ route('account.wallet') }}" class="hover:underline">{{ __('site.nav.wallet') }}</a>
                    <a href="{{ route('account.profile') }}" class="hover:underline">{{ __('site.nav.profile') }}</a>
                @endauth
            </div>
            <div class="ms-auto flex items-center gap-3 text-sm">
                <form method="POST" action="{{ route('language') }}" class="flex items-center gap-1">
                    @csrf
                    <label for="lang" class="sr-only">{{ __('site.nav.language') }}</label>
                    <select id="lang" name="locale" onchange="this.form.submit()" class="rounded-md border border-zinc-300 bg-white py-1 ps-2 pe-7 text-sm">
                        @foreach (config('app.locale_names') as $code => $label)
                            <option value="{{ $code }}" @selected(app()->getLocale() === $code)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <noscript><button class="btn-light py-1">OK</button></noscript>
                </form>
                @auth
                    <form method="POST" action="{{ route('logout') }}">@csrf<button class="hover:underline">{{ __('site.nav.logout') }}</button></form>
                @else
                    <a href="{{ route('login') }}" class="hover:underline">{{ __('site.nav.login') }}</a>
                    <a href="{{ route('register') }}" class="btn py-1.5">{{ __('site.nav.register') }}</a>
                @endauth
            </div>
        </nav>
    </header>

    <main class="mx-auto max-w-6xl px-4 py-8">
        {{ $slot }}
    </main>

    <footer class="mx-auto max-w-6xl px-4 pb-8 text-xs text-zinc-500">
        {{ __('site.common.times_note', ['tz' => config('app.timezone')]) }}
    </footer>
</body>
</html>
