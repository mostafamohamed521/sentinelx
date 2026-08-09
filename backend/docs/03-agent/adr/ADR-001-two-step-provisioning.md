# ADR-001: Agent Creation and API Key Issuance Are Two Sequential Calls, Never One Atomic Backend Operation

| | |
|---|---|
| **Status** | ✅ Accepted |
| **Scope** | Agent Module, Sprint 2 |
| **Affects** | `POST /agents`, `POST /agents/{id}/rotate-api-key`, the Agent Provisioning UX |

---

## Context

Setting up a new AI Agent to be monitored requires two things to exist: an `Agent` record and an `API Key`. From the Human operator's point of view, this should feel like one action — "create my agent, give me a key."

From the backend's point of view, `agents` is owned by the Agent module and `api_keys` is owned by the API Key submodule of Authentication, and the frozen dependency graph ([`05-module-dependencies.md`](../../00-backend_architecture/docs/backend/backend-architecture/05-module-dependencies.md) §4) states explicitly:

```text
API Key (submodule) → depends on → Agent
Agent  ✘→  API Key   (Agent does NOT depend on API Key)
```

---

## Decision

`POST /agents` creates the Agent only, and returns no credential material. Provisioning a usable Agent requires a second, explicit call to `POST /agents/{id}/rotate-api-key`, owned by the API Key submodule. The Dashboard (client) chains these two calls automatically so the operator still experiences it as a single flow.

---

## Rationale

### Why not have `CreateAgentAction` call `GenerateApiKeyAction` directly?
That would require the Agent module to know the Authentication module's Application layer exists — a direct violation of the frozen `Agent ✘→ API Key` rule. Even if implemented "just this once," it establishes a precedent that erodes the module boundary the very first time it's tested under real changes (e.g., swapping the credential mechanism in V2 — see `04-api-key-coordination.md` §6).

### Why not have the API Key submodule own the `POST /agents` endpoint instead, since it depends on Agent anyway?
Because Agent creation is not fundamentally a credential concern — an Organization needs to create, rename, and manage Agents regardless of how (or whether, in some hypothetical future integration mode) that Agent authenticates. Ownership belongs with the entity's actual business meaning, not with whichever module happens to be allowed to call the other.

### Why is this acceptable UX-wise?
Because the two-call chain is invisible to the operator when the client (Dashboard) performs it automatically, immediately, as part of the same on-screen "Create Agent" flow — the same pattern GitHub and Stripe use for "create a resource, then create a credential scoped to it" as two distinct, chainable API resources.

---

## Rejected Alternatives

| Alternative | Reason for Rejection |
|-------------|------------------------|
| Single atomic endpoint that creates both Agent and API Key in one DB transaction | Requires one module to directly depend on the other's Application layer, breaking the frozen `05-module-dependencies.md` rule |
| Move Agent creation into the Authentication module entirely | Misplaces ownership — Agent metadata management has nothing to do with proving identity, and would recreate a God Module inside Authentication |
| Have the Agent module emit a synchronous event that Authentication handles inline within the same HTTP request/response cycle | Technically avoids a direct method call, but still couples the request's success/failure and response shape to Authentication's internal behavior, and complicates error handling (what HTTP status wins if key generation fails but Agent creation succeeded?) — the two-call approach makes each failure mode explicit and independently retryable |

---

## Consequences

- ✅ The module boundary (`Agent ✘→ API Key`) is never violated, even implicitly.
- ✅ Each call has one clear success/failure mode — no ambiguous partial-success states to reason about.
- ✅ The `rotate-api-key` endpoint is reused, unmodified, for both "issue first key" and "issue replacement key" — no separate "issue initial key" endpoint needed.
- ⚠️ The client (Dashboard, or any other integrator) is responsible for chaining the two calls — this must be documented clearly in integration guides so a third-party API consumer doesn't stop after step 1 and wonder why their Agent has no usable key.
