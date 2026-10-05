<?php

namespace App\Http\Middleware;

use App\Services\PartnerService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// MD §8: the partner feature toggle gates the API, not just a button. Off globally or in the user's country pack → 403.
class EnsurePartnerModule
{
    public function __construct(private PartnerService $partners) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user() && $this->partners->enabledFor($request->user()), 403, __('messages.partner_disabled'));

        return $next($request);
    }
}
