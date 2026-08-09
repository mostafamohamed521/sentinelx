# 03 — Processing Pipeline

> Implements the pipeline already frozen in [`DATA_LIFECYCLE.md`](../docs/docs/08-database/03-DATA_LIFECYCLE.md), [`OBSERVATIONS_API.md`](../docs/docs/09-api-reference/04-OBSERVATIONS_API.md)'s Processing Pipeline diagram, and [`DEPLOYMENT_ARCHITECTURE.md`](../docs/docs/10-operational-architecture/01-DEPLOYMENT_ARCHITECTURE.md)'s `Database → Queue → ML Service` component chain.

---

## 1. The Frozen Pipeline, Annotated by Ownership (Continuing From Stage 3)

```text
Receive              → Observation module (Stage 3, done)
Authenticate          → Authentication module (Stage 2, done)
Validate               → Observation module (Stage 3, done)
Persist                 → Observation module (Stage 3, done)
    ↓
Queue                     → Analysis module — THIS STAGE
    ↓
ML Analysis                 → Analysis module — THIS STAGE
    ↓
Prediction Stored              → Analysis module — THIS STAGE
```

---

## 2. Why a Polling Worker, Not a Push From Observation

Per [`docs/backend/observation/05-cross-module-boundaries.md`](../04-observation/05-cross-module-boundaries.md) §2, the Observation module never calls out to Analysis — it only exposes write methods (`markProcessing`/`markCompleted`/`markFailed`) and a read contract (`ObservationLookupContract`) for Analysis to call *into* it. This means Analysis cannot be *notified* by Observation the moment a new row is inserted — it must actively look for work. [`01-database/schema/indexes.md`](../01-database/01-database/schema/indexes.md), Index 5, already reserves exactly this query, calling it *"the single most important query in the entire backend"*:

```sql
SELECT * FROM observations
WHERE analysis_status = 'PENDING'
ORDER BY received_at ASC
LIMIT 1;
```

See [`adr/ADR-001-polling-worker-not-push.md`](./adr/ADR-001-polling-worker-not-push.md) for the full reasoning.

---

## 3. The Two-Tier Pipeline: Poller + Queue

Matching [`DEPLOYMENT_ARCHITECTURE.md`](../docs/docs/10-operational-architecture/01-DEPLOYMENT_ARCHITECTURE.md)'s explicit `Database → Queue → ML Service` chain, this module is built as two cooperating pieces, not one:

```text
Tier 1 — Poller (a scheduled Artisan Command)
    │
    │ runs on a short interval (e.g. every few seconds, via Laravel's Scheduler)
    │
    ├── SELECT ... WHERE analysis_status = 'PENDING' ORDER BY received_at ASC LIMIT N
    │
    ├── for each claimed Observation:
    │       Observation\Infrastructure\ObservationRepository::markProcessing($id)
    │       (the exact method Stage 3 already exposed and left unconsumed)
    │
    └── dispatches AnalyzeObservationJob($observationId) onto the real Queue (Redis)


Tier 2 — Queue Worker (a standard Laravel queue worker process)
    │
    │ consumes AnalyzeObservationJob
    │
    ├── fetches the Observation via Observation\Application\Contracts\
    │     ObservationLookupContract (read-only, exposed by Stage 3, unconsumed until now)
    │
    ├── calls MLClient (Infrastructure layer, this module) — see 04-ml-client-contract.md
    │
    ├── on success: INSERT INTO predictions, then
    │     ObservationRepository::markCompleted($id, $processedAt)
    │
    └── on failure (after retries): ObservationRepository::markFailed($id, $processedAt)
```

**Why split into two tiers instead of one long-running process doing everything?** The Poller's only job is *claiming* work quickly and cheaply (a fast, indexed query plus a status flip) so two Poller runs never claim the same Observation twice. The actual ML call — the slow, failure-prone part — happens inside the Queue Worker, which Laravel's own queue infrastructure already retries, backs off, and monitors, without this module needing to reinvent any of that machinery.

---

## 4. Claiming Prevents Double-Processing

```text
Poller run N:     claims Observation X → marks PROCESSING → dispatches job
Poller run N+1:   the same query no longer returns X, because its analysis_status
                    is now PROCESSING, not PENDING — X is never claimed twice
```

This is exactly why `markProcessing` must happen **synchronously, inside the Poller**, before the job is merely dispatched onto the Queue — not inside the Queue Worker itself, where a delay between dispatch and execution could otherwise let a second Poller run re-claim the same row.

---

## 4a. Capacity (PERF-002)

The Poller runs `->everyMinute()` (`routes/console.php`) and claims at most `config('analysis.poll_batch_limit')` Observations per run (`ANALYSIS_POLL_BATCH_LIMIT`, defaulting to 10 — see `.env.example`). This is the platform's real, current throughput ceiling: **at most `ANALYSIS_POLL_BATCH_LIMIT` Observations are claimed for analysis per minute, globally, across every Organization** — not a per-Organization allowance. An adjustable engineering default, not a frozen business rule; raise it directly via the environment variable as real throughput needs become clearer.

**Deliberately not built in this pass:** per-Organization fairness (so one high-volume Organization can't starve every other Organization's queue of the shared batch). The current FIFO-by-`received_at` claim order (Index 5, `01-database/schema/indexes.md`) has no such guarantee. This is real architectural work — a fairness scheme needs its own design pass (round-robin per Organization? a per-Organization minimum reservation?) — and is flagged here as a follow-up decision, not silently deferred without a record.

---

## 5. Success and Failure Paths

```text
ML call succeeds
    │
    ├── INSERT INTO predictions (verdict, confidence, risk_score, summary,
    │     model_version, prediction_json, analyzed_at)   — all copied verbatim
    │     from the ML Engine's response (see 02-domain.md §4)
    │
    └── ObservationRepository::markCompleted(observationId, now())


ML call fails (after retries — see adr/ADR-003-ml-failure-retry-then-fail.md)
    │
    ├── NO Prediction row is ever written for this Observation
    │
    └── ObservationRepository::markFailed(observationId, now())
```

**A `FAILED` Observation is not automatically retried by a later Poller run** — `FAILED` is excluded from the Poller's `WHERE analysis_status = 'PENDING'` query by construction. Re-attempting a failed analysis is an explicit, separate, operator-triggered action: `php artisan analysis:retry-failed {observationId}` (or `--all` for every currently FAILED Observation), transitioning it back to `PENDING` so the next Poller run picks it up (STATE-004/FAILURE-003, integration audit Session 08). Deliberately a CLI/operator tool, not a REST endpoint — no Role/authorization model exists for "who can force a re-analysis," and building that API surface is a separate, future decision if self-service or Owner-triggered retry is ever needed.

---

## 6. What This Pipeline Deliberately Does Not Do

```text
✘ Does not notify the Human/Dashboard synchronously when analysis completes — Dashboard
  (Stage 6) will read the resulting state on its own schedule (page load, refresh, or a
  future real-time layer not in V1 scope)
✘ Does not create an Alert — that's Stage 5's job, reading this module's output, not
  triggered from inside this pipeline (see 05-cross-module-boundaries.md §2)
✘ Does not batch multiple Observations into a single ML request — the ML Contract is
  defined per-Observation (see 04-ml-client-contract.md); batching, if ever needed for
  throughput, is a future ML Contract version, not a Stage 4 concern
```

---

## 7. Full Sequence

See the rendered version at [`diagrams/prediction-pipeline-sequence.svg`](./diagrams/prediction-pipeline-sequence.svg).

```text
Poller               Observation Module          Queue          ML Engine        Analysis Repo
  │                        │                        │                │                 │
  │── SELECT PENDING ─────►│                        │                │                 │
  │◄── Observation X ──────│                        │                │                 │
  │── markProcessing(X) ──►│                        │                │                 │
  │── dispatch Job(X) ───────────────────────────────►│                │                 │
  │                        │                        │── consume ────►│                 │
  │                        │                        │                │── analyze ─────►│
  │                        │                        │                │◄── verdict, ... ─│
  │                        │                        │                │── INSERT Prediction
  │                        │◄── markCompleted(X) ───────────────────────────────────────│
```
