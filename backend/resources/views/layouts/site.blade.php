<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#4f46e5">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col overflow-x-clip bg-zinc-50 font-sans text-zinc-900 antialiased">
    @php($link = fn (string $route) => 'nav-link'.(request()->routeIs($route) ? ' nav-link-on' : ''))
    <header class="sticky top-0 z-30 border-b border-zinc-200/70 bg-white/80 backdrop-blur-lg" x-data="{ open: false }">
        <nav class="mx-auto flex max-w-6xl items-center gap-4 px-4 py-3">
            <a href="{{ route('home') }}" class="flex items-center gap-2 text-lg font-bold tracking-tight">
                <span class="grid h-9 w-9 place-items-center rounded-xl bg-gradient-to-br from-brand-500 to-brand-700 text-white shadow-md shadow-brand-600/30">
                    <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="6" cy="16" r="3"/><circle cx="18" cy="16" r="3"/><path d="M6 16l4-6h5l3 6M10 10L8.5 7H7M15 10l1.5-3H19"/></svg>
                </span>
                {{ config('app.name') }}
            </a>
            <button type="button" class="ms-auto rounded-lg p-2 text-zinc-600 hover:bg-zinc-100 md:hidden" @click="open = ! open" :aria-expanded="open" aria-label="Menu">
                <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>
            <div class="absolute inset-x-0 top-full flex-col gap-1 border-b border-zinc-200 bg-white p-3 text-sm shadow-lg md:static md:flex md:flex-1 md:flex-row md:items-center md:border-0 md:bg-transparent md:p-0 md:shadow-none"
                 :class="open ? 'flex' : 'hidden'">
                <a href="{{ route('home') }}" class="{{ $link('home') }}">{{ __('site.nav.search') }}</a>
                @auth
                    <a href="{{ route('account.bookings') }}" class="{{ $link('account.booking*') }}">{{ __('site.nav.my_bookings') }}</a>
                    <a href="{{ route('account.wallet') }}" class="{{ $link('account.wallet') }}">{{ __('site.nav.wallet') }}</a>
                    <a href="{{ route('account.profile') }}" class="{{ $link('account.profile') }}">{{ __('site.nav.profile') }}</a>
                    @if (app(\App\Services\PartnerService::class)->enabledFor(auth()->user()))
                        <a href="{{ route('partner.dashboard') }}" class="{{ $link('partner.*') }}">{{ __('site.nav.partner') }}</a>
                    @endif
                @endauth
                <div class="mt-2 flex flex-wrap items-center gap-2 border-t border-zinc-100 pt-3 md:ms-auto md:mt-0 md:border-0 md:pt-0">
                    <form method="POST" action="{{ route('language') }}">
                        @csrf
                        <label for="lang" class="sr-only">{{ __('site.nav.language') }}</label>
                        <select id="lang" name="locale" onchange="this.form.submit()" class="rounded-lg border border-zinc-200 bg-white py-1.5 ps-2.5 pe-8 text-sm">
                            @foreach (config('app.locale_names') as $code => $label)
                                <option value="{{ $code }}" @selected(app()->getLocale() === $code)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <noscript><button class="btn-light py-1">OK</button></noscript>
                    </form>
                    @auth
                        <form method="POST" action="{{ route('logout') }}">@csrf<button class="nav-link">{{ __('site.nav.logout') }}</button></form>
                    @else
                        <a href="{{ route('login') }}" class="{{ $link('login') }}">{{ __('site.nav.login') }}</a>
                        <a href="{{ route('register') }}" class="btn py-2">{{ __('site.nav.register') }}</a>
                    @endauth
                </div>
            </div>
        </nav>
    </header>

    <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-8">
        {{ $slot }}
    </main>

    <footer class="border-t border-zinc-200 bg-white">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-2 px-4 py-6 text-xs text-zinc-500">
            <span class="font-semibold text-zinc-700">© {{ date('Y') }} {{ config('app.name') }}</span>
            <span>{{ __('site.common.times_note', ['tz' => config('app.timezone')]) }}</span>
        </div>
    </footer>
</body>
</html>
