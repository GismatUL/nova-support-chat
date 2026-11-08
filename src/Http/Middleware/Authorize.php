<?php

namespace Gismatul\NovaSupportChat\Http\Middleware;

use Closure;
use Gismatul\SupportChat\SupportChat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline check for every support-chat route in Nova: the user must be allowed to view conversations.
 * Finer abilities (reply, manage) are checked per route.
 */
class Authorize
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Gate::forUser($request->user())->allows(SupportChat::ABILITY_VIEW), 403);

        return $next($request);
    }
}
