<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Locale = authenticated user's locale, else Accept-Language, else app default.
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = config('app.supported_locales');
        $userLocale = $request->user('sanctum')?->locale;

        app()->setLocale(in_array($userLocale, $supported, true)
            ? $userLocale
            : ($request->getPreferredLanguage($supported) ?? config('app.locale')));

        return $next($request);
    }
}
