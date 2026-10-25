<?php

namespace App\Http\Middleware;

use App\Services\InvestorService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// MD §7.11 / §8: the investor toggle gates the API, not just a button. Off globally or in the user's country pack → 403.
class EnsureInvestorModule
{
    public function __construct(private InvestorService $investors) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user() && $this->investors->enabledFor($request->user()), 403, __('messages.investor_disabled'));

        return $next($request);
    }
}
