<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Models live under App\Modules\{Module}\Infrastructure\Persistence
        // rather than App\Models, so Eloquent's default factory-name guesser
        // (which only strips the App\Models\ or App\ prefix) can't find
        // Database\Factories\{Model}Factory on its own — resolve by class
        // basename instead, matching every existing factory's name.
        Factory::guessFactoryNamesUsing(
            fn (string $modelName): string => 'Database\\Factories\\'.Str::afterLast($modelName, '\\').'Factory'
        );

        $this->registerRateLimiters();
    }

    /**
     * SECURITY-002: named, explicit rate-limiting policies for every v1
     * route, replacing three hand-picked `throttle:N,1` numbers plus an
     * entirely unthrottled majority (including the system's highest-volume,
     * externally-reachable endpoint) with one deliberate, documented set.
     * See docs/integration-audit/05-security-boundaries-audit.md.
     */
    private function registerRateLimiters(): void
    {
        // Unauthenticated auth-flow endpoints: IP-scoped, since there is no
        // caller identity yet. Limits unchanged from the pre-existing
        // ad hoc numbers — only the naming/discipline changed here.
        RateLimiter::for('auth-register', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('auth-login', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('auth-email-resend', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        // The Agent SDK's Observation ingestion route — the single
        // highest-volume, externally-reachable, adversary-reachable
        // endpoint in the system (per-Agent, not per-IP, since the real
        // caller identity here is the Agent's API Key, not a browser/IP —
        // many Agents may legitimately share an egress IP). A generous but
        // real ceiling, flagged as an adjustable engineering default, not a
        // frozen business rule (consistent with how this codebase already
        // flags similar decisions, e.g. ADR-ml-001's verdict thresholds).
        RateLimiter::for('observation-ingestion', fn (Request $request) => Limit::perMinute(300)
            ->by((string) ($request->user('agent')?->id ?? $request->ip()))
        );

        // Every other authenticated v1 route (Agents, Alerts, Dashboard,
        // Organization, Audit) — per-Human-user, since this group sits
        // entirely behind the JWT guard. Also an adjustable default.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)
            ->by((string) ($request->user('api')?->id ?? $request->ip()))
        );
    }
}
