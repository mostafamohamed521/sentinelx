# 03 — Agent Lifecycle & Provisioning Flow

---

## 1. The Four Lifecycle Actions

```text
Create Agent
    ↓
Update Agent (metadata only)
    ↓
Archive Agent
```

Plus one flow that spans two modules: **Provisioning** (Create Agent + obtain its first credential).

---

## 2. Create Agent

```text
Actor:        Human User, role OWNER (see 05-authorization.md)
Input:        name, framework, framework_version?, description?
Output:       Agent { id, name, framework, status: ACTIVE, created_at, ... }
                — no credential material of any kind
```

**Business rules enforced:**
```text
1. name must be unique within the caller's Organization → 409 CONFLICT otherwise
2. organization_id is never accepted from the request body — always taken from the
   Authenticated Identity (the JWT's resolved User → Organization), exactly the same
   principle applied to API Keys never accepting organization_id from the SDK
   (02-auth/05-api-keys.md §5)
3. status is always ACTIVE at creation — there is no "create as Archived"
```

**What this endpoint deliberately does NOT do:** generate an API Key. See §4.

---

## 3. Update Agent

```text
Actor:    Human User, role OWNER
Mutable:  name, framework, framework_version, description
Immutable: organization_id, status (status changes only via Archive), id, created_at
```

`PATCH`, not `PUT` — partial updates only, consistent with [`09-API_CONVENTIONS.md`](../docs/docs/09-api-reference/09-API_CONVENTIONS.md). Re-validates the `(organization_id, name)` uniqueness rule if `name` is part of the payload.

---

## 4. Archive Agent

```text
Actor:   Human User, role OWNER
Effect:  status: ACTIVE → ARCHIVED (terminal in V1)
Side effect: emits AgentArchived event → API Key submodule revokes the Agent's
             active key (see 02-domain.md §5)
```

**Why not DELETE?** Per [`09-API_CONVENTIONS.md`](../docs/docs/09-api-reference/09-API_CONVENTIONS.md): *"DELETE — Not used for business entities in Version 1."* An Agent's historical Observations, Predictions, and Alerts must remain queryable forever for audit purposes — deleting the Agent row would either cascade-destroy that history or leave orphaned foreign keys. See [`adr/ADR-002-archive-not-delete.md`](./adr/ADR-002-archive-not-delete.md).

**Idempotency:** calling Archive on an already-`ARCHIVED` Agent returns `409 CONFLICT` — not a silent 200. Silently succeeding would hide a caller's stale assumption about the Agent's state from them.

---

## 5. The Full Provisioning Flow (Create Agent → First Usable Credential)

This is the flow a Human actually walks through when setting up a new AI Agent to be monitored — and it deliberately spans two backend calls, not one, because of the module boundary described in [`04-api-key-coordination.md`](./04-api-key-coordination.md).

```text
Human (Dashboard)
    │
    │ 1. POST /api/v1/agents  { name, framework, ... }
    ▼
Agent module → creates Agent, status=ACTIVE
    │
    │ 201 Created — Agent object, NO key material
    ▼
Dashboard immediately, automatically
    │
    │ 2. POST /api/v1/agents/{agentId}/rotate-api-key
    ▼
Authentication (API Key submodule)
    │  - verifies the Agent exists, belongs to caller's Organization, is ACTIVE
    │  - generates a new API Key, marks any prior key REVOKED (none exists yet)
    │  - returns the raw key — exactly once
    ▼
201 Created — { key_prefix, raw_key, ... }  (raw_key never retrievable again)
    │
    ▼
Human copies raw_key into the SDK's configuration
```

See the rendered version at [`diagrams/agent-provisioning-sequence.svg`](./diagrams/agent-provisioning-sequence.svg).

> **From the Dashboard user's perspective this feels like one action** ("Create Agent" shows a "here is your key" screen immediately after). **From the backend's perspective it is two independent, sequential calls**, orchestrated by the client (Dashboard), never by one module silently reaching into another. This is exactly the same shape GitHub uses for "Create a fine-grained personal access token" versus "Create a repository" — two different resources, chained by the client.

---

## 6. Why Not Make the Backend Orchestrate This Atomically?

Considered and rejected — see [`adr/ADR-001-two-step-provisioning.md`](./adr/ADR-001-two-step-provisioning.md) for the full reasoning. In short: any single backend action that creates an Agent *and* an API Key in one transaction has to live in one module, and whichever module that is, it now depends on the other — breaking the frozen rule `Agent ✘→ API Key` from [`05-module-dependencies.md`](../00-backend_architecture/docs/backend/backend-architecture/05-module-dependencies.md) §4.

---

## 7. What Happens on Rotate (Agent Already Has a Key)?

Not this module's concern to implement, but relevant to know while building the Agent module's UI-facing contract: `POST /agents/{id}/rotate-api-key` is always safe to call again later — it always means *"give me a new key and kill the old one,"* whether this is the Agent's first key or its fifth. See [`02-auth/05-api-keys.md`](../02-auth/02-auth/05-api-keys.md) §8–§9.

---

## 8. Summary

```text
Agent Lifecycle

Create   → Agent module only, no credential
Update   → Agent module only, metadata fields
Archive  → Agent module writes status; emits event; API Key submodule reacts
Provision → two sequential calls, orchestrated by the client, never merged server-side
```
