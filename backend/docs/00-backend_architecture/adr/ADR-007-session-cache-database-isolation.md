# ADR-007: Session/Cache Isolation from the Primary Database Connection

| | |
|---|---|
| **Status** | ✅ Accepted |
| **Conflict Source** | Integration Audit Session 08, FAILURE-001 (Critical) |
| **Affects** | `.env.example`, `config/session.php`, `config/cache.php` (no code changes — both already read their driver from `SESSION_DRIVER`/`CACHE_STORE`) |

---

## Context

The integration audit (`docs/integration-audit/08-failure-scenarios-audit.md`, FAILURE-001) found that authentication (both the JWT and Agent-API-Key guards), the session driver, the cache driver, and the queue driver all resolved, per the shipped example configuration, to the same single PostgreSQL connection (`DB_CONNECTION=pgsql`, `SESSION_DRIVER=database`, `CACHE_STORE=database`, `QUEUE_CONNECTION=database`). A database outage was therefore not a localized failure — it simultaneously removed the ability to authenticate anyone (session lookups), serve cached data, and dispatch/process queued work, all at once, including the one resilience mechanism (the Analysis queue) that exists specifically to survive a *different* dependency's (the ML Service's) outages.

Full database high-availability/replication/failover is an infrastructure decision outside this codebase's scope. What is in scope is reducing blast radius by decoupling what doesn't need to share fate with the primary database connection.

## Decision

**Session and cache move to Redis.** `ext-redis` (phpredis) is already present in this project's PHP runtime and `config/database.php`'s `redis` connection block already exists and is fully configured via environment variables — no new Composer dependency or new config file was needed, only changing which driver `SESSION_DRIVER`/`CACHE_STORE` name (`.env.example`). Both `config/session.php` and `config/cache.php` already resolve their `default` store from these env vars, so no code change was required there either.

**The queue stays on the database connection.** This is a deliberate, accepted trade-off, not an oversight — see "Why not the queue too" below.

## Why Session/Cache First

- **Session**: if the database is down, already-authenticated users should not be silently logged out. Moving sessions to Redis means an in-progress user session survives a database outage; only new logins (which genuinely require the database, to look up the User row) are affected.
- **Cache**: the cache layer exists to reduce database load and improve latency; coupling it to the same database it's meant to protect defeats part of its own purpose during an outage, and offers no meaningful consistency benefit that would justify keeping it there.

## Why Not the Queue Too

Moving the queue to Redis as well was considered and deliberately deferred:

- The queue driver change has different operational stakes than session/cache — an in-flight `AnalyzeObservationJob` payload, its retry count, and its backoff schedule are business state (per ADR-003, `adr/ADR-003-ml-failure-retry-then-fail.md`), not disposable cache/session data. Migrating the queue driver warrants its own deliberate review of at-least-once delivery guarantees under the new driver, not a drive-by config change bundled into this fix.
- Per the audit's own framing, closing this finding only requires reducing blast radius, not achieving full isolation in one pass. Session/cache isolation already moves authentication and read-latency off the database's fate; the queue remaining coupled is a smaller, clearly-scoped remaining gap.

**This is a known, accepted risk**, stated here explicitly rather than left as the unstated assumption the audit found: `AnalyzeObservationJob` dispatch, its retry/backoff bookkeeping, and `ClaimPendingObservationsAction`'s claim query still share fate with the primary PostgreSQL connection. A database outage still stalls analysis processing entirely; it no longer also destroys sessions or the cache.

## Log Evidence Survives Independently

Application logging (`config/logging.php`, `LOG_CHANNEL=stack` → `LOG_STACK=single`, the `single` file channel) is already file-based, not database-backed. This was confirmed directly in `config/logging.php` rather than assumed — a database outage does not prevent Phase 4's structured logging (error envelopes, `metric` log entries, Alert-generation logs) from being written and remaining available for incident review.

## Consequences

- ✅ A primary-database outage no longer also destroys every already-authenticated user's session or the cache layer.
- ✅ No new infrastructure dependency was introduced — `ext-redis` and the `redis` connection config already existed in this codebase; only which driver `SESSION_DRIVER`/`CACHE_STORE` point at changed.
- ⚠️ A Redis instance is now a required runtime dependency for session/cache to function at all — this trades "total outage on DB failure" for "a second dependency to operate," a deliberate trade the audit's own recommendation endorsed.
- ⚠️ The queue (and therefore the Analysis pipeline) remains coupled to the primary database connection — an explicitly accepted, documented risk, not an oversight, and a candidate for a future, separately-scoped ADR if queue isolation is later prioritized.
