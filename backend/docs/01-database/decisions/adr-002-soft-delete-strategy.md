# ADR-002: Rejecting Soft Delete (`deleted_at`) in Favor of Explicit Business States

| | |
|---|---|
| **Status** | ✅ Accepted (Frozen) |
| **Session** | Session 3 (initial) + Session 6 (final confirmation) |
| **Affects** | All tables — especially `organizations`, `agents`, `alerts` |

---

## Context

A common pattern in many systems is adding a `deleted_at` column (Soft Delete) to every table, so a record remains physically present but is excluded from normal queries. This pattern was evaluated for the SentinelX database.

---

## Decision

**No `deleted_at` column exists in any table across the entire database.** Instead, every table that needs to represent "stopping" or "ending" an entity has an explicit **Business State** inside a `status` column:

```text
Organization  → SUSPENDED   (instead of DELETE)
Agent    → ARCHIVED     (instead of DELETE)
Alert    → RESOLVED     (instead of DELETE)
```

Tables with no real-world reason to be deleted at all (`observations`, `predictions`) have no deletion mechanism of any kind — neither soft nor hard.

---

## Rationale

### 1. The Business State Already Exists
The platform already has a clear lifecycle defined for every entity that needs to be "stopped":

```text
Agent  → ACTIVE → ARCHIVED
Alert  → OPEN → ACKNOWLEDGED → RESOLVED
Organization → ACTIVE → SUSPENDED
```

Adding `deleted_at` on top of this creates **two sources of truth** about the same record's state — is the record "inactive" because `status = ARCHIVED`, or because `deleted_at IS NOT NULL`? This is added complexity with no real value.

### 2. The Platform's Nature Is Security & Audit
SentinelX is a Security & Audit platform — **history matters more than disk space**. Whether or not we use soft delete, data in the critical tables (`observations`, `predictions`) will never actually be deleted. The only difference is that the true state is clearly represented via `status`, rather than through an extra, ambiguous flag.

### 3. Avoiding Over-Engineering
Soft delete is a useful pattern in certain contexts (e.g., a user deleting their own content), but in SentinelX's context — where there's no deletion in any business flow to begin with — adding it introduces complexity (query scopes, exceptions in every query) without serving any real use case.

---

## Rejected Alternatives

| Alternative | Reason for Rejection |
|-------------|------------------------|
| `deleted_at` on every table | Creates duplication with the business states that already exist, and complicates every query with an extra exclusion condition |
| Physical Delete | Entirely unacceptable on a Security & Audit platform — permanently loses the historical evidence trail |

---

## Consequences

- ✅ Simpler queries — no need for `WHERE deleted_at IS NULL` everywhere.
- ✅ The record's true state is clear and direct from the `status` column itself.
- ✅ Aligns with the [Archive Instead of Delete](../architecture/design-principles.md#4-archive-instead-of-delete) principle.
- ⚠️ Requires the application team (Backend) to strictly commit to never issuing an actual `DELETE` against the critical tables — this constraint is not technically enforced at the database level; it's an architectural agreement that must be honored in the code layer.
