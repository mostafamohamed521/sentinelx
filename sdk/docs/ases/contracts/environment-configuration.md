# Contract: Environment & Configuration

> This is an implementation-ready specification, derived directly from [`12-packaging-and-distribution.md`](../12-packaging-and-distribution.md) and the Configuration Service described in [`08-internal-architecture.md`](../08-internal-architecture.md).

---

## 1. Governing Principle

> **Exactly one component reads environment variables directly: `config/settings.py`. Every other component reads configuration only through it — never independently.**

This is a hard architectural rule (see [`11-repository-architecture.md`](../11-repository-architecture.md#domain-5--config)), not a convention.

---

## 2. Supported Environment Variables

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `ASES_API_KEY` | ✅ (unless supplied via `configure()` or the `ASES(...)` constructor) | none | The Agent's API Key, issued during Agent registration (see [`05-customer-integration-journey.md`](../05-customer-integration-journey.md#stage-3--register-agent)) |
| `ASES_ENDPOINT` | ❌ | SentinelX production API URL | Overrides the target API endpoint. The customer should never need to set this on the Happy Path |
| `ASES_ENVIRONMENT` | ❌ | `"production"` | A free-form label (`development`, `staging`, `production`) used for the customer's own environment separation |

---

## 3. Configuration Precedence

When more than one source supplies a value (most relevant for `api_key`), precedence is, highest to lowest:

```text
1. Explicit argument to ASES(api_key=...) or configure(api_key=...)
2. ASES_API_KEY environment variable
3. (no further fallback — missing api_key is a configuration error)
```

---

## 4. `configure()` Contract

```python
from ases import configure

configure(api_key: str = None) -> None
```

- May be called **at most once** per process, before any `ASES(...)` instance is constructed.
- Calling it more than once should raise a clear configuration error rather than silently overwriting the prior call — configuration is meant to be set once and read everywhere (see [`08-internal-architecture.md`](../08-internal-architecture.md#7-what-is-a-service-vs-a-component)).
- Arguments not supplied fall back to their corresponding environment variable.

---

## 5. `.env` File Support

Standard Python `.env` file conventions are supported, consistent with customary practice for Python libraries (`fastapi`, `requests`, etc. — see [`12-packaging-and-distribution.md`](../12-packaging-and-distribution.md#2-why-pypi-specifically)):

```env
ASES_API_KEY=sk_live_ab12_9f8e7d6c5b4a3928...
ASES_ENDPOINT=https://api.sentinelx.example.com
ASES_ENVIRONMENT=production
```

A `.env.example` file ships at the repository root (see [`11-repository-architecture.md`](../11-repository-architecture.md#8-the-complete-package-structure)) documenting all three variables with placeholder values.

---

## 6. Validation

`Settings` (the sole reader of environment configuration) is responsible for:

```text
1. Reading all three variables (and/or explicit configure()/constructor arguments).
2. Validating that api_key is present from at least one source.
3. Applying the ASES_ENDPOINT default if not overridden.
4. Failing fast, with a clear error message, if api_key is missing entirely —
   never allowing the SDK to silently start in an unconfigured state.
```

---

## 6a. Key Rotation and Revocation — Deliberate V1 Scope (RC-8, IDENTITY-003)

The Backend supports rotating and revoking an Agent's API Key as a live, real-time operation (`POST /agents/{agentId}/rotate-api-key` — the previous key becomes invalid immediately). The SDK does not build live key-reload to track this — the same "avoid over-engineering" discipline this document set already applies elsewhere (e.g. the deliberate absence of a CLI, `11-repository-architecture.md §7`), and there is no current customer requirement driving it.

**The accepted V1 behavior, stated explicitly rather than left silent:**

```text
1. A running SDK process holds its configured api_key for the process's
   entire lifetime — resolved once, at Settings.resolve() time, and never
   re-read afterward.
2. If that key is rotated or revoked on the Backend while the process is
   still running, every subsequent Observation send fails with the
   authentication-failure handling defined in 10-transport-layer.md's
   Retry Policy (a clear, actionable log warning, immediate drop, no
   wasted retries) — not undefined behavior.
3. Picking up a new key requires restarting the process with the new
   ASES_API_KEY (or a new configure()/ASES(api_key=...) call in a fresh
   process). There is no in-process reconfiguration path in V1.
```

This is a deliberate V1 scope decision, stated with the same directness `ADR-003-in-memory-observation-buffering.md` states its own accepted-loss-on-crash tradeoff — not an oversight, and not something apologized for.

---

## 7. What Is Explicitly Not Configurable in V1

Per [`10-transport-layer.md`](../10-transport-layer.md#5-the-transport-lifecycle) and [`ADR-004-non-blocking-async-transport.md`](../adr/ADR-004-non-blocking-async-transport.md):

```text
✘ Retry count (fixed at 3)
✘ Queue size or persistence behavior
✘ Shutdown flush timeout (fixed at 5 seconds — RC-9, RELIABILITY-002)
✘ Per-request send timeout (fixed at 10 seconds — RC-9, RELIABILITY-001)
```

These remain internal implementation details, not customer-facing configuration, consistent with the Public API's "Intent, not Implementation" principle (see [`04-public-api.md`](../04-public-api.md#10-the-decision-that-outlives-this-document)).
