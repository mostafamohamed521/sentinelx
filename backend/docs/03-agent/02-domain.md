# 02 — Agent Domain

> Restates, for the implementation layer, exactly what is already frozen in [`01-database/02-schema/entities.md`](../01-database/01-database/02-schema/entities.md) §3 — adds nothing new to the schema, only the business-rule detail the schema file intentionally leaves out.

---

## 1. The Agent Entity

```text
Agent
──────────────────────
id                  UUID v7, PK
organization_id     FK → organizations.id
name                string, required
framework           string, required   (e.g. CrewAI, LangGraph, OpenAI Agents SDK, AutoGen)
framework_version   string, optional
description         text, optional
status              ACTIVE | ARCHIVED
last_seen_at        timestamp, optional — set by the Observation pipeline, never by this module directly (see §5)
created_at          timestamp
updated_at          timestamp
```

`UNIQUE(organization_id, name)` — scoped uniqueness. See [`adr/ADR-003-name-uniqueness-scope.md`](./adr/ADR-003-name-uniqueness-scope.md).

---

## 2. Field-Level Business Rules

| Field | Rule |
|-------|------|
| `name` | Required. 1–255 chars. Must be unique **within the Organization**, not globally. |
| `framework` | Required. Free-text string in V1 — not an enum. A closed list of frameworks would need updating every time a new agent framework becomes popular; validated for presence only, never for membership in a fixed list. |
| `framework_version` | Optional free-text (e.g. `"1.2.0"`). No semantic versioning validation in V1. |
| `description` | Optional, unbounded text. Purely for the human operator's own reference. |
| `status` | Set to `ACTIVE` at creation. Can only ever transition to `ARCHIVED`. There is no path back from `ARCHIVED` to `ACTIVE` in V1 — see [`03-lifecycle.md`](./03-lifecycle.md) §3. |
| `last_seen_at` | Never written by this module. Written exclusively by the Observation module every time this Agent successfully submits an Observation. The Agent module only ever *reads* it for display purposes. |

---

## 3. Why `last_seen_at` Lives on `agents` But Isn't Owned by This Module's Write Path

This looks like a contradiction at first glance and deserves to be spelled out explicitly, because it is the kind of detail that gets implemented wrong.

```text
Column location:    agents.last_seen_at   (physically on this module's table)
Write authority:     Observation module   (via a narrow, explicit interface — never a direct UPDATE from Observation's own repository reaching across tables)
Read authority:      Agent module          (for display: "Last seen 2 minutes ago")
```

**Concrete implementation rule:** the Observation module's `StoreObservationAction` calls a narrow, explicitly-exposed method — e.g. `AgentRepository::touchLastSeen(agentId, timestamp)` — that lives inside the Agent module's own Infrastructure layer and is the *only* legal way any other module ever writes to the `agents` table. No other module ever runs a raw `UPDATE agents SET ...` of its own. This preserves "modules do not communicate with each other's database tables" ([`03-system-modules.md`](../00-backend_architecture/docs/backend/backend-architecture/03-system-modules.md) §6) while still allowing this one legitimate cross-module write.

---

## 4. State Machine

```text
        create
          │
          ▼
      ┌────────┐
      │ ACTIVE │◄────────────┐
      └───┬────┘              │
          │ archive            │  (no reverse transition in V1)
          ▼                   │
     ┌───────────┐             │
     │ ARCHIVED  │─────────────┘   (dead end — intentionally)
     └───────────┘
```

See [`diagrams/agent-status-state.svg`](./diagrams/agent-status-state.svg) for the rendered version, and [`adr/ADR-002-archive-not-delete.md`](./adr/ADR-002-archive-not-delete.md) for why there is no `DELETE`.

### Allowed Transitions Table

```text
ACTIVE   → ARCHIVED   ✔ allowed  (via PATCH /agents/{id}/archive)
ARCHIVED → ACTIVE     ✘ not allowed in V1
ARCHIVED → ARCHIVED   ✘ idempotency handled at the Application layer — a second archive
                          call on an already-archived Agent returns 409 CONFLICT,
                          not a silent success (see 06-api-contract.md)
```

---

## 5. What Happens to a Related API Key When an Agent Is Archived?

The Agent module does **not** reach into `api_keys` to revoke it directly — that would violate the module boundary (§4 of `01-overview.md`). Instead:

```text
ArchiveAgentAction (Agent module)
    │
    ├── 1. Transition agents.status → ARCHIVED
    │
    └── 2. Emit an "AgentArchived" domain event
              │
              ▼
        API Key submodule (Authentication) listens
              │
              ▼
        Any ACTIVE key for that Agent → REVOKED
```

This event-based handoff is the only mechanism by which Archiving an Agent has any effect on its API Key — it is Authentication's own decision to react to the event, not Agent reaching into Authentication's table. See [`04-api-key-coordination.md`](./04-api-key-coordination.md) for the full mechanics and why this direction is the only one allowed.

---

## 6. What an Agent Does NOT Have

```text
✘ A Role (Roles belong to Users only — see 02-auth/06-authorization.md §12)
✘ A password
✘ Multiple simultaneous "active" identities
✘ The ability to log in to the Dashboard
```

An Agent has exactly one Capability once authenticated (via its API Key, owned by Authentication): `Submit Observation`. Nothing about that Capability is stored on the `agents` table itself.

---

## 7. Domain Invariants (Must Hold True At All Times)

```text
1. Every Agent belongs to exactly one Organization — never zero, never many.
2. (organization_id, name) is always unique.
3. status is always exactly one of ACTIVE | ARCHIVED — never NULL.
4. An ARCHIVED Agent can still be read and listed — it is never hidden from history.
5. An ARCHIVED Agent can never submit a new Observation (enforced by Authentication,
   via the "Archived Agent" auth-failure case already frozen in
   02-auth/contracts/auth-errors.md).
```
