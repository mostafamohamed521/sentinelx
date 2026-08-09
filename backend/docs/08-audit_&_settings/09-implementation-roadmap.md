# 09 — Implementation Roadmap

> Converts everything designed in this folder into a build plan. Spans three modules at once — the only Sprint in this series to do so — following the exact same rule set and Layer order from [`06-implementation-layers.md`](../00-backend_architecture/00-backend_architecture/06-implementation-layers.md) applied independently to each.

---

## 1. Where This Sits

```text
Sprint 0 — Foundation           ✅ Done
Sprint 1 — Identity Foundation  ✅ Done
Sprint 2 — Agent Foundation     ✅ Done
Sprint 3 — Observation Pipeline ✅ Done
Sprint 4 — ML Integration       ✅ Done
Sprint 5 — Alert Engine         ✅ Done
Sprint 6 — Dashboard            ✅ Done
Sprint 7 — Audit + Settings     🟢 This roadmap — FINAL SPRINT
```

**Definition of Done for Sprint 7 and for the entire MVP** (per [`08-sprint-roadmap.md`](../00-backend_architecture/00-backend_architecture/08-sprint-roadmap.md) §9 and §14): *"the system is ready as a complete MVP,"* verified by the full, zero-mock scenario: `Organization Registration → User Login → Create Agent → Generate API Key → SDK sends Observation → Validation → Store → Send to ML → Receive Prediction → Generate Alert → Dashboard displays Result → User reviews Observation History`.

---

## 2. Build Order — Three Modules, in Dependency Order

```text
FIRST: Organization module (nothing in this Sprint depends on it being new, but
        it's the most foundational of the three, and Authentication's existing
        RegisterAction verification, per 04-organization-settings.md §4, should
        happen before anything else)

1. Domain      → Organization invariants (already implicitly exist since Stage 1)
2. Infrastructure → OrganizationModel (likely already exists from Stage 1 — confirm,
                      don't blindly recreate), OrganizationRepository
                      (findByIdForOrganization... actually just findCurrent(),
                      update()), CreateOrganizationAction (verify/extract per
                      04-organization-settings.md §4)
3. Application  → UpdateOrganizationAction
4. Presentation → OrganizationResource
5. API          → OrganizationController, UpdateOrganizationRequest, routes
                    (auth:jwt, role:owner for PATCH)


SECOND: Authentication extension (small, self-contained)

1. Application  → UpdateProfileAction, ChangePasswordAction
2. Presentation → (reuses existing UserResource from Stage 1)
3. API          → extend the existing AuthController (or a new ProfileController),
                    UpdateProfileRequest, ChangePasswordRequest, routes


THIRD: Audit module (new — depends conceptually on every other module's events,
        but has no CODE dependency on any of them — see 03-audit-logging.md §2)

1. Domain      → the audit_logs invariants (02-domain.md §4)
2. Infrastructure → AuditLogModel, AuditRepository (create(), listForOrganization()
                      with filters, findById())
3. Application  → RecordAuditEventAction (generic — takes an already-normalized
                    {action, resource_type, resource_id, metadata} + the
                    AuthenticatedIdentity), ListAuditLogsAction,
                    GetSecurityLogsAction (thin wrapper, per 06-security-logs.md §3)
4. Infrastructure/Listeners → one listener per audited event, per the exhaustive
                                list in 03-audit-logging.md §3-4:
     RecordOrganizationUpdated, RecordUserRegistered, RecordUserLoggedIn,
     RecordUserLoggedOut, RecordProfileUpdated, RecordPasswordChanged,
     RecordAgentCreated, RecordAgentUpdated, RecordAgentArchived (a SECOND listener
       on the Stage-2 AgentArchived event — Stage 2's own code is untouched),
     RecordApiKeyGenerated, RecordApiKeyRotated, RecordApiKeyRevoked,
     RecordAlertAcknowledged, RecordAlertResolved
5. Presentation → AuditLogResource
6. API          → AuditController, SecurityLogController (or one controller, two
                    actions), routes (auth:jwt, role:owner|admin)
```

---

## 3. New Events Required in Already-Frozen Modules

Unlike every prior Sprint's "one small addition," this Sprint requires a genuinely larger set of new event dispatches across almost every prior module — this is expected, given Audit's job is specifically to observe everything:

```text
Organization module   → dispatch OrganizationUpdated after UpdateOrganizationAction
Authentication module   → dispatch UserRegistered, UserLoggedIn, UserLoggedOut,
                             UserProfileUpdated, UserPasswordChanged at their
                             respective existing/new action points
Agent module               → dispatch AgentCreated, AgentUpdated after their
                                respective existing Actions (AgentArchived already
                                exists from Stage 2 — Audit just adds itself as a
                                second listener, no change to that event itself)
Authentication (API Key)      → dispatch ApiKeyGenerated, ApiKeyRotated,
                                   ApiKeyRevoked after their respective existing Actions
Alert module                    → dispatch AlertAcknowledged, AlertResolved after
                                     their respective existing Actions
```

**Every one of these is a single `dispatch()` line added after an already-existing, already-working Action's core logic** — exactly the same shape as every prior Sprint's small additive change, just more of them at once, because this is the Sprint where the pattern's "everyone eventually reports to the passive recorder" shape is fully realized.

---

## 4. What Sprint 7 Explicitly Does NOT Build

```text
✘ Observation or Prediction audit events (see 03-audit-logging.md §3 — deliberately
  excluded, already recorded elsewhere)
✘ A slug-change endpoint, or any URL/subdomain migration logic
✘ A self-service Organization suspend/reactivate flow
✘ Team Management (invite/remove members, change another User's Role) — not
  scoped anywhere in the frozen documentation for V1
✘ Forced re-authentication / JWT invalidation on password change (flagged as a
  future consideration in 05-profile.md §6, not solved here)
✘ Any export/download mechanism for audit logs (CSV, PDF) — not specified anywhere
```

---

## 5. Tests Required (Following the Engineering Workflow's Five Categories)

```text
1. Happy Path
   ✔ Owner can view and update Organization settings
   ✔ Any Role can view and update their own profile, and change their own password
   ✔ Owner/Admin can list and view Audit Logs and Security Logs
   ✔ Every audited action (Organization update, Agent create/update/archive, API Key
     generate/rotate/revoke, Alert acknowledge/resolve, User register/login/logout/
     profile update/password change) produces exactly one correctly-shaped
     audit_logs row

2. Edge Case
   ✔ PATCH /organization attempting to set slug or status → 422, rejected outright
   ✔ change-password with an incorrect current_password → 401, no row updated
   ✔ change-password with mismatched confirmation → 422
   ✔ GET /security-logs never returns a "organization.updated" or
     "agent.archived" entry, even though those exist in the general Audit Log

3. Business Rule
   ✔ audit_logs rows are never updated or deleted by any endpoint — assert no
     UPDATE/DELETE route exists for this resource at all
   ✔ A failure inside the Audit listener (simulate a DB error) does not roll back
     or fail the triggering action (e.g., Agent creation still succeeds and
     returns 201 even if its accompanying audit write fails) — this is the single
     most important test in this Sprint, directly verifying the Golden Rule from
     01-overview.md §2

4. Authorization
   ✔ Member attempting PATCH /organization → 403
   ✔ Member attempting GET /audit-logs or GET /security-logs → 403 (the first
     Member-gets-403-on-a-GET test in this entire series — confirm it's
     deliberate, per 07-authorization.md, not a regression)
   ✔ Admin succeeds at GET /audit-logs and GET /security-logs (confirm Admin is
     NOT excluded, only Member)
   ✔ Unauthenticated requests → 401 across every endpoint in this Sprint

5. Data Isolation
   ✔ Organization A's audit/security logs never include Organization B's entries
   ✔ A User cannot PATCH /me or change-password for any User other than themselves
     (there is no user_id parameter anywhere on these routes to even attempt this
     with — verify the route design itself prevents the question from arising)
```

---

## 6. Sprint 7 (Final) Exit Checklist

```text
☐ NEW migration: audit_logs table, per 02-domain.md §1 — the only new table in
  this entire series
☐ Stage 1's actual Organization-creation code path verified/reconciled per
  04-organization-settings.md §4
☐ UpdateOrganizationAction, UpdateProfileAction, ChangePasswordAction implemented
☐ Every event listed in §3 above dispatched from its respective existing Action
☐ Every corresponding Audit listener implemented and tested
☐ GetSecurityLogsAction confirmed to reuse AuditRepository, not a second table
☐ All endpoints from 08-api-contract.md implemented, with the correct
  (and, for Audit/Security Logs, deliberately asymmetric) Role gates
☐ The FULL end-to-end MVP scenario from 09-sprint-roadmap.md §14 runs with zero
  mocks: Organization Registration → Login → Create Agent → Generate API Key →
  SDK Observation → Validation → Store → ML → Prediction → Alert → Dashboard →
  Observation History — AND, new for this Sprint, every step along that chain
  produces a corresponding, correctly-scoped Audit Log entry
☐ docs/backend/audit-settings/ (this folder) marked Frozen
☐ SentinelX MVP — complete
```
