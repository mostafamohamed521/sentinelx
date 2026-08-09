# ADR-003: Owner, Admin, and Member All Have Identical Access to the Dashboard

| | |
|---|---|
| **Status** | ✅ Accepted (Engineering Default — Consistent With Two Prior Precedents) |
| **Scope** | Dashboard Module |
| **Affects** | The Role check on `GET /dashboard` |

---

## Context

As with [`docs/backend/alert/adr/ADR-003-all-roles-can-act-on-alerts.md`](../../06-alert/adr/ADR-003-all-roles-can-act-on-alerts.md), no frozen document provides an explicit per-endpoint Role matrix for the Dashboard. Unlike that prior case, however, this decision has an unusually strong, near-mechanical argument available: every individual piece of data `GET /dashboard` aggregates is *already* visible to all three Roles through its own underlying endpoint.

---

## Decision

`Owner`, `Admin`, and `Member` all have identical, full access to `GET /dashboard` — no Role-based restriction.

---

## Rationale

### Why is this decision easier than the equivalent one for Alerts?
Because it can be derived mechanically rather than by interpretation. `docs/backend/agent/05-authorization.md` §2 already establishes all three Roles can `GET /agents`. `docs/backend/observation/06-authorization.md` §2 already establishes all three Roles can `GET /observations`. `docs/backend/alert/04-authorization.md` §4 already establishes all three Roles can `GET /alerts`. `GET /dashboard` contains strictly less detail than any one of those three endpoints individually (a 5-item recent list, not a full paginated history; a count, not the full record set) — there is no way for restricting the aggregate to a subset of Roles to serve any actual security purpose, since nothing in the aggregate isn't already independently visible to a `Member` through the underlying endpoints.

### Is there any reason Dashboard access specifically might warrant tighter restriction than its underlying data?
None identified in any frozen document. If anything, a plausible argument runs the opposite direction: an aggregated summary view is a *lower*-detail, lower-risk artifact than the full underlying data streams a `Member` can already query — so restricting the summary while leaving full access to its ingredients would be a strictly worse, more confusing authorization posture, not a more careful one.

---

## Rejected Alternatives

| Alternative | Reason for Rejection |
|-------------|------------------------|
| Restrict `GET /dashboard` to `Owner`/`Admin` only | No security benefit — a `Member` can already reconstruct everything in the aggregate by calling the three underlying endpoints individually; the restriction would only add friction, not protection |
| Show a reduced version of the Dashboard to `Member` (e.g., omit `organization_stats`) | No frozen document motivates this distinction, and it would be inconsistent with `Member` already having full access to every underlying data source the reduced fields would be computed from |

---

## Consequences

- ✅ Consistent with the identical reasoning already applied to Alerts — the third and strongest instance of this pattern in the series.
- ✅ Simplest possible authorization implementation: a single "is this an authenticated Human" check, no Role branching logic anywhere in this module.
- ⚠️ As with the Alert precedent, if a genuine future requirement introduces Role-tiered Dashboard views (e.g., a `Member`-specific simplified layout, for UX reasons rather than security ones), that's a Presentation-layer/frontend concern, not a reason to revisit this backend authorization decision.
