# SentinelX — Agent Module Documentation

> **Status:** 🟢 **Active Design — Stage 2 of the Implementation Order**
> **Depends on (Frozen):** `docs/backend/backend-architecture/` (Baseline v2.0), `docs/backend/database/` (01-database), `docs/backend/authentication/` (02-auth)
> **Owner:** Backend Architecture Team
> **Extends, never conflicts with:** the frozen `docs.zip` root documentation. Nothing in this folder introduces a new table, a new module boundary, or a new business rule that isn't already implied by the frozen baseline — it only adds the implementation-level detail the baseline deliberately left out.

---

## 1. Why does this folder exist?

Exactly the same reason `02-auth` exists: this is not a tutorial, and it does not explain code that already exists. It is the **Source of Truth an engineer (human or Claude Code) reads before writing a single line of the Agent module**, so that implementation requires zero improvisation and zero redesign.

> **If there is ever a conflict between this folder and `docs.zip`, `docs.zip` wins — this folder must be corrected, not the other way around.**

---

## 2. Where This Sits in the Roadmap

```text
Stage 0 — Foundation                     ✅ Done
Stage 1 — Organization + Authentication  ✅ Done
Stage 2 — Agent + API Key submodule      🟢 THIS FOLDER
Stage 3 — Observation Pipeline           ⏳ Next
Stage 4 — Analysis                       ⏳
Stage 5 — Alert                          ⏳
Stage 6 — Dashboard                      ⏳
Stage 7 — Audit & Settings               ⏳
```

Per [`backend-architecture/07-implementation-order.md`](../00-backend_architecture/docs/backend/backend-architecture/07-implementation-order.md):

> "Why before Observation? Because Observation ingestion is impossible without an Agent and an API Key."

---

## 3. The Core Idea in One Sentence

> **The Agent module owns the Agent's Identity and Lifecycle. It owns nothing about how that Agent proves itself on the wire — that remains the API Key submodule's job, inside Authentication.**

This single sentence is the reason this folder is scoped the way it is, and the reason every "who does what" question below resolves the same way, every time.

---

## 4. Module Boundary Recap (from the Frozen Baseline)

```text
Agent Module                          Authentication Module (API Key submodule)
────────────────────                  ──────────────────────────────────────────
✔ Agent (entity)                      ✔ API Key (entity)
✔ Agent Status (ACTIVE/ARCHIVED)      ✔ Key Hash
✔ Agent Metadata (name, framework…)   ✔ Rotation History

✘ Does NOT own API Keys               ✘ Does NOT own Agent metadata
✘ Does NOT own Observations           ✘ Does NOT own Observations
```

Dependency direction (per [`05-module-dependencies.md`](../00-backend_architecture/docs/backend/backend-architecture/05-module-dependencies.md), unchanged here):

```text
Agent
    ↓
Organization

API Key (submodule)
    ↓
Agent
    ↓
Organization
```

**Agent does not depend on API Key. API Key depends on Agent.** This is why this folder never describes Agent creation and API Key issuance as a single atomic backend operation — see [`03-lifecycle.md`](./03-lifecycle.md) and [`adr/ADR-001-two-step-provisioning.md`](./adr/ADR-001-two-step-provisioning.md).

---

## 5. Folder Architecture

```text
03-agent/
│
├── README.md                          ← you are here
│
├── 01-overview.md                     ← What the Agent module is (and isn't)
├── 02-domain.md                       ← Agent entity, states, invariants
├── 03-lifecycle.md                    ← Create / Update / Archive / Provisioning flow
├── 04-api-key-coordination.md         ← The exact Agent ↔ API Key boundary in practice
├── 05-authorization.md                ← Who can do what to an Agent
├── 06-api-contract.md                 ← Full endpoint-by-endpoint contract
├── 07-implementation-roadmap.md       ← Build order, Sprint 2 breakdown
│
├── adr/
│   ├── ADR-001-two-step-provisioning.md
│   ├── ADR-002-archive-not-delete.md
│   └── ADR-003-name-uniqueness-scope.md
│
└── diagrams/
    ├── agent-status-state.svg
    └── agent-provisioning-sequence.svg
```

---

## 6. Recommended Reading Order

| # | File | What you'll learn |
|---|------|--------------------|
| 1 | [`01-overview.md`](./01-overview.md) | What the Agent module is responsible for, and the one rule it never breaks |
| 2 | [`02-domain.md`](./02-domain.md) | The Agent entity, its fields, its two-state lifecycle, its invariants |
| 3 | [`03-lifecycle.md`](./03-lifecycle.md) | Create, Update, Archive, and the full Provisioning flow (with the SDK) |
| 4 | [`04-api-key-coordination.md`](./04-api-key-coordination.md) | Exactly how Agent and API Key cooperate without violating the module boundary |
| 5 | [`05-authorization.md`](./05-authorization.md) | Role checks per endpoint, and why an Agent itself never manages Agents |
| 6 | [`06-api-contract.md`](./06-api-contract.md) | Every endpoint, request/response shape, status codes, error cases |
| 7 | [`07-implementation-roadmap.md`](./07-implementation-roadmap.md) | Build order inside Sprint 2, mapped to Layers |
| — | [`adr/`](./adr) | The three pivotal decisions for this module, with rejected alternatives |
| — | [`diagrams/`](./diagrams) | State diagram, provisioning sequence diagram |

---

## 7. What This Folder Deliberately Does NOT Redefine

Everything below is already frozen elsewhere and is only **referenced**, never restated or altered:

```text
agents table schema                → 01-database/02-schema/entities.md
api_keys table schema              → 01-database/02-schema/entities.md
API Key format & lifecycle         → 02-auth/05-api-keys.md, contracts/api-key-format.md
Roles (Owner / Member)             → 02-auth/06-authorization.md
Error response shape               → docs.zip/09-api-reference/07-ERROR_CODES.md
Pagination shape                   → docs.zip/09-api-reference/08-PAGINATION.md
API conventions (no DELETE, v1…)   → docs.zip/09-api-reference/09-API_CONVENTIONS.md
Layer structure (API→…→Presentation) → backend-architecture/06-implementation-layers.md
```

---

## 8. Design Status

```text
Agent Module Design
████████████████████████████ 100% (ready for implementation)

Overview                ✅ Frozen
Domain                  ✅ Frozen
Lifecycle               ✅ Frozen
API Key Coordination    ✅ Frozen
Authorization           ✅ Frozen
API Contract            ✅ Frozen
Implementation Roadmap  ✅ Frozen
```

> As with `02-auth`, once this folder is used to generate code, it becomes frozen too. Any future change must come from a real business requirement (V2), not a mid-implementation rethink.
