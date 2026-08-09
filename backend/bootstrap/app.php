<?php

use App\Http\Middleware\AssignRequestId;
use App\Modules\Agent\Domain\Exceptions\AgentAlreadyArchivedException;
use App\Modules\Agent\Domain\Exceptions\AgentForbiddenException;
use App\Modules\Agent\Domain\Exceptions\AgentNameConflictException;
use App\Modules\Agent\Domain\Exceptions\AgentNotActiveException;
use App\Modules\Agent\Domain\Exceptions\AgentNotFoundException;
use App\Modules\Alert\Domain\Exceptions\AlertAlreadyAcknowledgedException;
use App\Modules\Alert\Domain\Exceptions\AlertAlreadyResolvedException;
use App\Modules\Alert\Domain\Exceptions\AlertNotFoundException;
use App\Modules\Analysis\Infrastructure\Queue\PollPendingObservationsCommand;
use App\Modules\Analysis\Infrastructure\Queue\RetryFailedObservationsCommand;
use App\Modules\Audit\Domain\Exceptions\AuditForbiddenException;
use App\Modules\Audit\Domain\Exceptions\AuditLogNotFoundException;
use App\Modules\Authentication\Identity\API\Middleware\EnsureUserHasRole;
use App\Modules\Authentication\Identity\Domain\AuthenticationFailedException;
use App\Modules\Authentication\Identity\Domain\AuthorizationFailedException;
use App\Modules\Authentication\Identity\Domain\Exceptions\InvalidCurrentPasswordException;
use App\Modules\Observation\Domain\Exceptions\MalformedObservationPayloadException;
use App\Modules\Observation\Domain\Exceptions\ObservationNotFoundException;
use App\Modules\Observation\Domain\Exceptions\ObservationValidationFailedException;
use App\Modules\Organization\Domain\Exceptions\OrganizationForbiddenException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        // Lives inside the Analysis module's own Infrastructure/Queue layer
        // rather than app/Console/Commands — every module keeps its own
        // pieces together, per 06-implementation-layers.md §2.
        PollPendingObservationsCommand::class,
        RetryFailedObservationsCommand::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);

        // Global and first, so request_id is stashed on the Request (and in
        // Log::withContext()) before any route middleware, controller, or
        // exception has a chance to run. See AssignRequestId's own doc-block.
        $middleware->prepend(AssignRequestId::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Every authentication failure on api/* returns the same generic
        // shape, regardless of cause — see contracts/auth-errors.md §1-2.
        // Reuses AuthenticationFailedException's own render() directly
        // rather than a second hand-written copy of the shape (ERROR-004) —
        // this callback's trigger (a missing/invalid token at the guard
        // layer) is a different cause than that Domain exception's own
        // explicit throw sites (e.g. a bad password), but the response
        // must look identical either way, so it is now defined in exactly
        // one place.
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return (new AuthenticationFailedException)->render($request);
            }
        });

        // Every FormRequest validation failure on api/* returns the
        // platform-wide nested VALIDATION_ERROR envelope — previously only
        // StoreAgentRequest/UpdateAgentRequest opted into this per-class,
        // and the other six FormRequests in the codebase silently fell
        // back to Laravel's structurally different default shape
        // (ERROR-001). One global choke point means no future FormRequest
        // can silently reintroduce that gap.
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'The given data was invalid.',
                        'details' => $e->errors(),
                    ],
                ], 422);
            }
        });

        // A database-layer failure never reaches a Controller's own error
        // handling anywhere in this codebase — falling through today to
        // Laravel's generic default response, which (in a misconfigured
        // APP_DEBUG=true environment — ERROR-007, Phase 5's concern, not
        // fixed here) could leak the underlying query/connection detail.
        // This response never includes the exception's message, file, or
        // trace — a generic, safe message only. Genuinely reported (see
        // below): this is not a routine, by-design business outcome like
        // OBS-004's list, it is a real infrastructure failure worth
        // investigating. See FAILURE-002.
        $exceptions->render(function (QueryException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'error' => [
                        'code' => 'SERVICE_UNAVAILABLE',
                        'message' => 'The service is temporarily unavailable. Please try again shortly.',
                        'details' => [],
                    ],
                ], 503);
            }
        });

        // Final catch-all for api/* — the same trace-free guarantee as the
        // QueryException render above, for any other genuinely unexpected
        // exception (a raw PHP TypeError/Error, an uncaught third-party
        // exception, ...). Deliberately does NOT intercept
        // HttpExceptionInterface instances (an undefined route's 404, a
        // 405, a 429, ...) — those already render safely and meaningfully
        // via Laravel's own default handling and are routine, expected
        // outcomes of normal traffic, not the "something genuinely broke"
        // case this catch-all exists for. Every Domain exception with its
        // own render() method, and every more specific callback registered
        // above, is resolved before this is ever reached (see
        // Illuminate\Foundation\Exceptions\Handler::render()'s own
        // method_exists('render') check and renderViaCallbacks()'s
        // first-match-wins order) — this is genuinely the last resort.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*') || $e instanceof HttpExceptionInterface) {
                return null;
            }

            return response()->json([
                'error' => [
                    'code' => 'SERVICE_UNAVAILABLE',
                    'message' => 'The service is temporarily unavailable. Please try again shortly.',
                    'details' => [],
                ],
            ], 503);
        });

        // Runs after every other render()/renderable() callback above (and
        // the framework's own default renderer) has already produced a
        // Response — the one place that can inject request_id into EVERY
        // error shape this API returns today, nested or flat, without
        // touching each individual exception's render() method. A thrown
        // exception never reaches AssignRequestId's own "after" half (see
        // its doc-block), so the header is set here too, not left to that
        // middleware. See ERROR-003/OBS-001.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return $response;
            }

            $requestId = $request->attributes->get('request_id') ?? (string) Str::uuid();
            $response->headers->set('X-Request-Id', $requestId);

            $data = json_decode($response->getContent() ?: 'null', true);

            if (is_array($data)) {
                if (isset($data['error']) && is_array($data['error'])) {
                    $data['error']['request_id'] = $requestId;
                } else {
                    $data['request_id'] = $requestId;
                }

                $response->setContent(json_encode($data));
            }

            return $response;
        });

        // Every Domain exception below represents an expected, by-design
        // business outcome (a routine 4xx a caller is meant to receive
        // during normal operation) — not suppressing these meant every one
        // of them was logged identically to a genuine, unexpected crash,
        // drowning out the signal an incident responder actually needs
        // (OBS-004). Deliberately NOT included: InvalidMlResponseException
        // (never reaches here at all — always caught internally by
        // AnalyzeObservationAction, per its own docblock; reaching this
        // handler would itself be a bug) and MLCommunicationException
        // (propagates intentionally so its exhaustion is visible via
        // Laravel's own job-failure reporting, per ADR-003 — suppressing
        // that would defeat the mechanism its retry policy relies on).
        $exceptions->dontReport([
            AgentAlreadyArchivedException::class,
            AgentForbiddenException::class,
            AgentNameConflictException::class,
            AgentNotActiveException::class,
            AgentNotFoundException::class,
            AlertAlreadyAcknowledgedException::class,
            AlertAlreadyResolvedException::class,
            AlertNotFoundException::class,
            AuditForbiddenException::class,
            AuditLogNotFoundException::class,
            AuthenticationFailedException::class,
            AuthorizationFailedException::class,
            InvalidCurrentPasswordException::class,
            MalformedObservationPayloadException::class,
            ObservationNotFoundException::class,
            ObservationValidationFailedException::class,
            OrganizationForbiddenException::class,
        ]);
    })->create();
