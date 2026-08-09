# ADR-002: Agents Are Archived, Never Deleted

| | |
|---|---|
| **Status** | ✅ Accepted |
| **Scope** | Agent Module |
| **Affects** | Agent lifecycle, `agents.status` enum, all downstream Observation/Prediction/Alert history |

---

## Context

An Agent accumulates Observations, Predictions, and Alerts over its operational life — this is, in fact, the entire point of the platform: a historical, auditable record of an AI Agent's behavior. `agents.status` is defined in the frozen schema ([`01-database/02-schema/entities.md`](../../01-database/01-database/02-schema/entities.md) §3) as `ACTIVE | ARCHIVED` only — no `DELETED`. The platform-wide API convention ([`09-API_CONVENTIONS.md`](../../docs/docs/09-api-reference/09-API_CONVENTIONS.md)) already states: *"DELETE — Not used for business entities in Version 1."*

---

## Decision

An Agent that is no longer in use is transitioned to `ARCHIVED` via `PATCH /agents/{id}/archive`. There is no `DELETE /agents/{id}` endpoint, and no code path anywhere removes an `agents` row.

---

## Rationale

### Why does deleting an Agent threaten historical integrity?
`observations.agent_id` is a required foreign key. Deleting an Agent would force a choice between cascading the delete (destroying the entire security history that justified the platform's existence) or leaving orphaned foreign keys (breaking every downstream query, dashboard, and audit trail). Neither is acceptable for a security-monitoring product whose core value proposition is historical evidence.

### Why not soft-delete with a `deleted_at` column instead of reusing `status`?
Considered, and rejected for consistency with the already-frozen decision on the `users` table ([`01-database/02-schema/entities.md`](../../01-database/01-database/02-schema/entities.md) §2): *"No `deleted_at` — `DISABLED` is the state that represents a user no longer active."* The same reasoning applies identically to `agents.status = ARCHIVED`. Introducing a second, parallel "is this thing gone" mechanism (`deleted_at` alongside `status`) for one entity but not others breaks the Naming/Schema Conventions' consistency goal for no real benefit.

### Why is there no path back from `ARCHIVED` to `ACTIVE`?
An explicit V1 scope decision, matching the Agent's own `03-lifecycle.md` §4. If a genuine "un-archive" business need emerges, it is a V2 requirement, not something to speculatively support now (the project's own stated principle: *"the Simple Solution is better than the Complex Solution, unless the Complex one is actually justified"*).

---

## Rejected Alternatives

| Alternative | Reason for Rejection |
|-------------|------------------------|
| Hard `DELETE`, cascading to Observations/Predictions/Alerts | Destroys the exact audit history the platform exists to provide |
| Hard `DELETE`, restricted (blocked if any Observations exist) | Still eventually blocks every real Agent from ever being removed, since almost all Agents will have submitted at least one Observation — makes the feature effectively useless while adding complexity |
| Separate `deleted_at` timestamp column | Duplicates what `status` already expresses; inconsistent with the `users` table's already-frozen precedent |
| Allow `ARCHIVED → ACTIVE` reactivation in V1 | Not a validated V1 business requirement; adds a state-machine edge case with no current use case to justify it |

---

## Consequences

- ✅ Every Observation, Prediction, and Alert ever produced by an Agent remains queryable indefinitely, regardless of the Agent's current operational status.
- ✅ Consistent with the identical decision already made for `users.status`.
- ✅ Simpler state machine — exactly two states, one directed edge.
- ⚠️ An Organization can accumulate an unbounded number of `ARCHIVED` Agents over time with no cleanup mechanism in V1 — acceptable for now; a future retention/export policy (Stage 7, Audit & Settings) can address this without requiring any schema change.
