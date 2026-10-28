<?php

use App\Http\Controllers\InstallController;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureInvestorModule;
use App\Http\Middleware\EnsurePartnerModule;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\RedirectToInstaller;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\SetWebLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api_v1.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // The installer runs outside the web group: no session or cookies until there is a database and an APP_KEY.
        then: function (): void {
            Route::get('/install', [InstallController::class, 'show'])->name('install');
            Route::post('/install', [InstallController::class, 'store']);
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(append: [SetLocale::class]);
        $middleware->web(prepend: [RedirectToInstaller::class], append: [SetWebLocale::class, EnsureActiveUser::class]);
        $middleware->alias(['role' => EnsureUserHasRole::class, 'partner.module' => EnsurePartnerModule::class, 'investor.module' => EnsureInvestorModule::class]);
        // API guests get a 401 JSON response, never a redirect; website guests go to the sign-in page.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : route('login'));
        $middleware->redirectUsersTo(fn () => route('home'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        // Business-rule refusals (unavailable vehicle, illegal status transition, invalid coupon…) are 422s.
        $exceptions->render(fn (DomainException $e, Request $request) => $request->is('api/*')
            ? response()->json(['message' => $e->getMessage()], 422)
            : null);
    })->create();
