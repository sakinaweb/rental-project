<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Website locale: the language picked in the switcher, else the signed-in user's locale, else the browser's (D6).
class SetWebLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = config('app.supported_locales');
        $chosen = collect([$request->session()->get('locale'), $request->user()?->locale])
            ->first(fn ($locale) => in_array($locale, $supported, true));

        app()->setLocale($chosen ?? $request->getPreferredLanguage($supported) ?? config('app.locale'));

        return $next($request);
    }
}
