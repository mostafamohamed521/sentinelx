# ADR-002: Human Identity Layer Is Added to the Baseline; Teams Excluded, Invitations Deferred

| | |
|---|---|
| **Status** | ✅ Accepted (Documentation Baseline v2.0) |
| **Conflict Source** | Cross-Review, Conflict 2 |
| **Affects** | Domain Model, Database Schema, Security Model, Backend Architecture module set, Feature Inventory |

---

## Context

Documentation Baseline v1.0 describes a platform authenticated **only** by Agent API Keys. Its Domain Model, Database Schema (`organizations, agents, api_keys, observations, predictions, alerts` — no `users` table), Entity Reference, and Security Model contain no Human Identity, no Login, no JWT, no Roles at all — despite a single, disconnected `09-api-reference/02-AUTH_API.md` file describing a `/login` + JWT + `/me` flow for a "dashboard user" that nothing else in the frozen baseline supports.

A full, independent Authentication design series (9 sessions) was completed after that freeze, defining Human Identity, Password/JWT authentication, RBAC, and Organization onboarding via Invitations — none of which was reflected back into the v1.0 baseline.

---

## Decision

**The Baseline is updated, not the newer Authentication design.** Human Identity becomes a first-class part of the core architecture:

```text
✅ Added to Baseline v2.0:
   Users
   Authentication
   Authorization
   Roles (Owner, Admin, Member — simple RBAC)

❌ Excluded from V1:
   Team Management (as a distinct feature)

🟡 Deferred to a future version:
   Invitations
```

---

## Rationale

### This Is a Gap, Not a Genuine Disagreement
The freeze happened while the team was still focused on the AI pipeline (Observation → ML → Alert) and had not yet designed the user-facing journey. The correct sequence should have been `Freeze → after Authentication Design`, but it happened the other way around. The baseline is simply missing a phase of work that came later — it isn't wrong on purpose.

### Human Identity Is Not Optional
The platform cannot function without it. Something has to `Register an Organization`, `Create an Agent`, `Generate an API Key`, and `view the Dashboard` — and that something is necessarily a human. Without a User entity, the platform as designed is literally impossible to operate.

### Why Exclude Teams But Keep RBAC?
`Team Management` (multi-member collaboration features) adds real scope for V1 that isn't yet required — a single Owner account is sufficient to operate an Organization end-to-end for the MVP. However, the Role model (`Owner`, `Admin`, `Member`) is kept in the baseline now, at minimal cost, so the schema and every Authorization check are already future-proofed for the moment additional members are introduced.

### Why Defer Invitations Specifically?
Invitations are the mechanism by which a *second* user joins an Organization. Since Team Management itself is deferred, the mechanism for growing a team is deferred alongside it. **Practical consequence:** in V1, every Organization is provisioned with exactly one User — its Owner, created at registration — and there is no in-product flow yet for that Owner to add teammates.

---

## Rejected Alternatives

| Alternative | Reason for Rejection |
|-------------|------------------------|
| Keep the v1.0 baseline as-is (Agent-only auth), roll back the Authentication design | Makes the platform impossible to operate — someone has to be able to log in and manage the Organization |
| Include full Team Management + Invitations in V1 | Adds real implementation scope (invitation lifecycle, email delivery, multi-member permission edge cases) that isn't required to ship a working MVP |
| Drop RBAC entirely since there's only one user per Organization in V1 | Would require reintroducing Roles later as a breaking schema change; keeping the enum now costs almost nothing and avoids future rework |

---

## Consequences

- ✅ The platform is now actually operable end-to-end (a human can register, log in, and manage the Organization).
- ✅ The Role model is future-proofed for Team Management / Invitations without requiring a schema change when they ship.
- ⚠️ The previously delivered Authentication documentation (9 sessions, already written up in full) specs Team Management and Invitations as **core V1 features** — this is now inconsistent with this ADR and needs a follow-up revision to mark those sections as deferred, rather than current scope.
- ⚠️ `docs.zip`'s Domain Model, Database Schema, Entity Reference, and Security Model must be updated to include `Users` — tracked as a follow-up sync task, not performed silently as part of this Backend Architecture delivery.
