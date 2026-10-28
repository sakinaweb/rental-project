<?php

namespace App\Http\Middleware;

use App\Support\Installer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Until the web installer has run, every page goes there (it can't work without a database anyway).
class RedirectToInstaller
{
    public function handle(Request $request, Closure $next): Response
    {
        return Installer::installed() ? $next($request) : redirect('/install');
    }
}
