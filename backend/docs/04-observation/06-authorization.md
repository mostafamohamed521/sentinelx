# 06 — Observation Authorization

> Applies [`02-auth/06-authorization.md`](../02-auth/02-auth/06-authorization.md) (Baseline v2.0 — three-tier Role model: `Owner`, `Admin`, `Member`) to the Observation module specifically.

---

## 1. Two Actor Types, Two Completely Different Rules

Unlike the Agent module (Human-only endpoints), the Observation module is the **one place in the system where both actor types genuinely meet**: Agents submit, Humans view.

```text
Agent (API Key)   → Submit  (write)
Human (JWT)        → View    (read)
```

Per [`02-auth/06-authorization.md`](../02-auth/02-auth/06-authorization.md) §5: *"An Agent has essentially one permission... Submit Observation."* This module is the literal implementation of that one permission.

---

## 2. Permission Matrix

| Action | Owner | Admin | Member | Agent (API Key) |
|--------|:-----:|:-----:|:------:|:----------------:|
| `POST /observations` | ❌ | ❌ | ❌ | ✅ (own Observations only) |
| `GET /observations` (list) | ✅ | ✅ | ✅ | ❌ |
| `GET /observations/{id}` | ✅ | ✅ | ✅ | ❌ |
| `GET /agents/{id}/observations` | ✅ | ✅ | ✅ | ❌ |

**A Human, regardless of Role, can never submit an Observation.** This isn't a missing permission to grant later — it's structurally impossible, because `POST /observations` only accepts API Key authentication (see [`03-ingestion-pipeline.md`](./03-ingestion-pipeline.md) §2). There is no Role check to write for this endpoint at all; the JWT guard simply never matches this route.

**An Agent, regardless of which Agent, can never view any Observation.** Same structural argument in reverse — `GET /observations*` only accepts JWT authentication. An Agent's single Capability is `Submit Observation` and nothing else, per [`02-auth/06-authorization.md`](../02-auth/02-auth/06-authorization.md) §12.

**All three Human Roles have identical read access to Observations.** Unlike the Agent module (where `Owner` alone can mutate), there is no mutation possible here at all — Observations are immutable (see [`02-domain.md`](./02-domain.md) §3) — so there is no Role tier for which "can view" would ever need to be withheld. Viewing security history is not an administrative privilege; it is the core value the platform provides to every seat in the Organization.

---

## 3. Agent-Level Data Isolation (Not Just Organization-Level)

A subtlety worth calling out explicitly, because it's easy to under-scope this: authorization for `POST /observations` isn't just "does this API Key belong to this Organization" — it's "does this API Key belong to *this exact Agent*, submitting *its own* Observation." There is no concept of one Agent submitting on behalf of another, ever:

```text
AuthenticatedIdentity.id  (the Agent ID resolved from the API Key)
    =
observations.agent_id     (always, no exceptions, never accepted from the request body)
```

This mirrors [`ADR-002-Agent-Identity`](../docs/docs/07-adrs/ADR-002-Agent-Identity.md), already frozen: *"Agent identity is resolved exclusively through the authenticated Agent API Key. The Observation payload never includes Organization ID or Agent ID."*

---

## 4. Organization Scoping (Human Read Side)

Same pattern as every other module so far:

```text
❌ Observation::find($observationId)
✅ $organization->observations()->where('id', $observationId)->firstOrFail()
```

**404, not 403, for an Observation belonging to a different Organization** — Security Through Obscurity, exactly as already established in [`docs/backend/agent/05-authorization.md`](../03-agent/05-authorization.md) §4.

---

## 5. Where the Check Executes

```text
Agent request:
Request → API Key Authentication → Capability Check (fixed: Submit) → ReceiveObservationAction

Human request:
Request → JWT Authentication → Role Check (any of Owner/Admin/Member — always passes) →
    Organization Scoping (enforced inside the query, not as a separate gate) → Action
```

The "Role Check" step for the read endpoints is trivial by design — it exists structurally (for consistency with every other module's middleware stack) but never actually excludes any authenticated Human, since all three Roles have identical read rights here.

---

## 6. Summary

```text
Observation Module Authorization

✔ Agent  → can submit only its own Observations; cannot read anything
✔ Owner / Admin / Member → identical read access (list, single, per-Agent history)
✔ No Human, of any Role, can ever submit an Observation
✔ Every read query scoped to Organization — 404 on cross-tenant access, never 403
✔ agent_id on write is always the authenticated Agent's own ID — never client-supplied
```
