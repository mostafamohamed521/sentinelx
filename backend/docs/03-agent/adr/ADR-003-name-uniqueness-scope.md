# ADR-003: Agent `name` Is Unique Per Organization, Not Globally

| | |
|---|---|
| **Status** | ✅ Accepted |
| **Scope** | Agent Module |
| **Affects** | `agents` table constraint, `POST /agents` and `PATCH /agents/{id}` validation |

---

## Context

The frozen schema already defines `UNIQUE(organization_id, name)` on `agents` ([`01-database/02-schema/entities.md`](../../01-database/01-database/02-schema/entities.md) §3, Key Decisions). This ADR exists to document the *implementation-level* reasoning behind that already-frozen constraint, so it is never "fixed" into a global-uniqueness constraint by a future engineer who hasn't seen the original database design session.

---

## Decision

`name` uniqueness is enforced at the `(organization_id, name)` composite level. Two different Organizations may each have an Agent named `"Support Agent"` simultaneously. Within a single Organization, no two Agents may share a name.

---

## Rationale

### Why scope to Organization instead of a global constraint?
`name` is a human-friendly, operator-chosen label ("Support Agent", "Fraud Detector") — not a system identifier. Organizations are fully isolated tenants (see [`01-database/03-decisions/adr-005-multi-tenancy.md`](../../01-database/01-database/03-decisions/adr-005-multi-tenancy.md)); there is no legitimate reason one Organization's naming choices should ever constrain another's. A global constraint would leak information across tenants (an Organization would learn that `"Support Agent"` is already taken *somewhere on the platform*, revealing the existence of other tenants) and would create an unnecessary support burden as the platform grows.

### Why enforce this at the database level (a constraint) and not only in application code?
Consistent with the project's general pattern (see the Engineering Workflow's business-rule vs. constraint distinction): uniqueness that must hold under concurrent writes belongs at the database level. Two simultaneous `POST /agents` requests for the same name in the same Organization must not both succeed — a race condition that only a database-level `UNIQUE` constraint fully closes. The Application layer still validates proactively (for a fast, friendly `409` instead of a raw database error surfacing), but the constraint is the actual source of truth.

---

## Rejected Alternatives

| Alternative | Reason for Rejection |
|-------------|------------------------|
| Global uniqueness across all Organizations | Leaks cross-tenant information; unnecessarily restrictive; no product requirement calls for it |
| No uniqueness constraint at all (allow duplicate names within an Organization) | Makes the Dashboard's Agent list confusing and error-prone for an operator trying to identify a specific Agent by name |
| Application-layer check only, no database constraint | Vulnerable to a race condition under concurrent creation requests |

---

## Consequences

- ✅ Organizations are fully independent in their naming choices — no cross-tenant leakage.
- ✅ `POST /agents` and `PATCH /agents/{id}` can return a fast, specific `409 CONFLICT` rather than a generic `500` from an unhandled database constraint violation, provided the Application layer checks proactively before insert/update — but the database constraint remains the final guarantee under concurrency.
- ⚠️ If an Organization renames Agent A to a name currently held by Agent B (also within the same Organization), the rename must fail with `409` — this is already covered in [`06-api-contract.md`](../06-api-contract.md) §4 and must be tested explicitly (see [`07-implementation-roadmap.md`](../07-implementation-roadmap.md) §4).
