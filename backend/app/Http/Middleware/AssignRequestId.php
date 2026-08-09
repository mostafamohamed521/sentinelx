<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives every request a correlation identifier — reused from an inbound
 * X-Request-Id header when the caller already supplies one (useful once the
 * Frontend or an SDK starts generating its own), otherwise generated here.
 *
 * Stashed on the request (not just returned) so the exception-response
 * pipeline (see bootstrap/app.php's `respond()` callback) can still read it
 * on a path that throws before this middleware's "after" half ever runs —
 * global middleware's "before" half always runs first, but a downstream
 * exception unwinds straight past the "after" half without invoking it.
 *
 * Pushed into Log::withContext() so every subsequent Log:: call in this
 * request carries request_id automatically, without each call site having to
 * pass it explicitly. See docs/integration-audit/
 * 07-observability-debuggability-audit.md OBS-001 and
 * 04-error-handling-consistency-audit.md ERROR-003.
 */
class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->header('X-Request-Id') ?: (string) Str::uuid();

        $request->attributes->set('request_id', $requestId);
        Log::withContext(['request_id' => $requestId]);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
