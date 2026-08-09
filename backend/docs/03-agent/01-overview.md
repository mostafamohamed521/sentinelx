# 01 — Agent Module Overview

> Extends [`backend-architecture/03-system-modules.md`](../00-backend_architecture/docs/backend/backend-architecture/03-system-modules.md) and [`04-module-responsibilities.md`](../00-backend_architecture/docs/backend/backend-architecture/04-module-responsibilities.md). Nothing here contradicts them.

---

## 1. What Is an Agent, In One Sentence?

> **An Agent is the real, ongoing client of the SentinelX platform — a registered AI system whose behavior is being observed.** The Human User who registered it is an operator, not the subject being watched.

This single sentence is why the `agents` table exists independently of `users`, and why — per [`01-database/02-schema/entities.md`](../01-database/01-database/02-schema/entities.md) — an Agent is described as *"conceptually closer to a Service Account than a regular User."*

---

## 2. What the Agent Module Is Responsible For

```text
Create Agent
Update Agent (mutable metadata only)
Archive Agent
View Agent (single + list, scoped to Organization)
List Observations submitted by an Agent (routing only — see §5)
```

## 3. What the Agent Module Is Explicitly NOT Responsible For

```text
✘ Generating, rotating, or revoking API Keys      → Authentication (API Key submodule)
✘ Validating incoming Observations                 → Observation module
✘ Deciding whether an Agent's behavior is risky     → Analysis module
✘ Hard-deleting an Agent                            → never, by design (see ADR-002)
```

If a future engineer's instinct is "the API Key belongs to the Agent, so Agent should manage it" — that instinct is exactly the mistake [`04-module-responsibilities.md`](../00-backend_architecture/docs/backend/backend-architecture/04-module-responsibilities.md) warns about:

> "A commonly mishandled boundary — the Agent module never owns credential or analysis data."

---

## 4. The One Rule This Module Never Breaks

> **The Agent module has zero knowledge of how an Agent authenticates.**

It knows an Agent *exists*, its *name*, its *framework*, and its *status*. It does not know what an API Key looks like, how it's hashed, or how many an Agent has had. This is deliberate — see [`adr/ADR-001-two-step-provisioning.md`](./adr/ADR-001-two-step-provisioning.md) for what this means in practice for the "Create Agent" flow.

---

## 5. Route Ownership vs. Module Ownership (Important)

The public API groups everything under `/api/v1/agents/...` for REST ergonomics — this **does not** mean the Agent module implements every one of those routes. Three different modules sit behind the same URL prefix:

| Route | Implemented By | Why |
|-------|------------------|-----|
| `GET /agents` | **Agent module** | Owns the entity |
| `POST /agents` | **Agent module** | Owns creation |
| `GET /agents/{id}` | **Agent module** | Owns the entity |
| `PATCH /agents/{id}` | **Agent module** | Owns mutable metadata |
| `PATCH /agents/{id}/archive` | **Agent module** | Owns the lifecycle |
| `POST /agents/{id}/rotate-api-key` | **Authentication (API Key submodule)** | Owns the credential; allowed to depend on Agent (§4 of `05-module-dependencies.md`) |
| `GET /agents/{id}/observations` | **Observation module** | Owns Observations; `Observation → Agent` is the permitted dependency direction, never the reverse |

This table is the single most important thing to internalize before touching routing — see [`06-api-contract.md`](./06-api-contract.md) for full detail per endpoint, and [`04-api-key-coordination.md`](./04-api-key-coordination.md) for the reasoning behind the API-Key row specifically.

---

## 6. Why Agent Is Its Own Module and Not Part of Authentication

Considered and rejected. An Agent is a **business entity** with metadata (`name`, `framework`, `description`) that has nothing to do with proving identity — an Organization needs to see and manage its fleet of Agents even independent of any credential concern (e.g., renaming an Agent, archiving one that's decommissioned). Folding this into Authentication would recreate exactly the kind of God Module the baseline architecture explicitly rejected in Session 3.

---

## 7. Session Summary

```text
Agent Module — Overview

Owns
✔ Agent entity
✔ Agent lifecycle (Active / Archived)
✔ Agent metadata

Does Not Own
✘ API Keys
✘ Observations
✘ Analysis / Risk

Golden Rule
✔ Agent has zero knowledge of authentication mechanics.
✔ Route grouping ≠ Module ownership.
```
