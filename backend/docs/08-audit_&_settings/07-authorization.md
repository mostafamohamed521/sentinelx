# 07 — Authorization

> The first genuinely **asymmetric** Role decision in this entire series. Every prior gap-filled authorization decision (Alert's ADR-003, Dashboard's ADR-003) landed on "all three Roles equal" — this Sprint is the first where that reasoning doesn't hold, and the reasons why are worth reading carefully.

---

## 1. Why This Sprint Breaks the Pattern

Every symmetric decision so far rested on the same argument: *"a Member could already reconstruct this view from data they can already see elsewhere, so restricting the aggregate/action serves no security purpose."* That argument does not apply here:

```text
Audit Logs / Security Logs   → this data is NOT visible anywhere else. Unlike
                                  Alerts or Observations, there is no existing
                                  endpoint a Member could use to piece together
                                  "who did what, when" across the Organization.
                                  This is new information, not a re-presentation
                                  of something already accessible.

Organization Settings (update) → renaming the Organization is a foundational,
                                    low-frequency, high-consequence action —
                                    closer in kind to Stage 2's original,
                                    genuinely-frozen "Owner: create Agents, rotate
                                    API Keys" precedent than to Stage 5/6's
                                    "viewing/acting on results" precedent.
```

---

## 2. The Decisions

```text
GET  /organization          → Owner, Admin, Member (viewing basic org info is
                                 harmless, same reasoning as every prior "view"
                                 decision)
PATCH /organization           → Owner ONLY

GET  /me, PATCH /me,            → any authenticated Human, for THEIR OWN record only
POST /me/change-password           — not a Role question at all; every Role can
                                       always manage their own profile

GET /audit-logs, GET             → Owner, Admin ONLY — Member excluded
  /audit-logs/{id}
GET /security-logs                → Owner, Admin ONLY — Member excluded
```

---

## 3. Why `Organization Settings` (Update) Is Owner-Only

Matches the real, frozen precedent from Stage 2 (*"day-to-day platform operation (create Agents, rotate API Keys...)"* attributed to Owner) more closely than the "everyone" precedent from Stages 5/6. Renaming the Organization itself is not "working with results" (Member's own frozen definition) — it's closer to a foundational identity decision, the kind of thing [`02-auth/06-authorization.md`](../02-auth/02-auth/06-authorization.md) §7 attributes to *"the organization's founder/owner"* specifically.

---

## 4. Why Audit/Security Logs Are Owner+Admin, Not Member

Two independent reasons, either one sufficient on its own:

**Reason 1 — It's new information, not a re-presentation.** As stated in §1, this breaks the mechanical argument that made every prior "everyone" decision easy. A Member gains genuinely new visibility (into every other team member's actions) that no other endpoint in the entire backend provides.

**Reason 2 — It's oversight/governance data, not "results."** `Member`'s frozen definition is *"views results and works with them."* Security findings (Observations, Alerts, Predictions) are unambiguously "results" — the product's core value. An audit trail of *"which team member did what, when"* is a different category of information: internal governance and accountability data, closer to `Admin`'s frozen definition of *"manages the platform's day-to-day operation."* Restricting it to `Owner`/`Admin` is the more natural reading of the existing Role definitions, not an arbitrary new restriction invented from nothing.

---

## 5. Why `Admin` Is Included Here (Not Owner-Only, Like Organization Settings)

Because `Admin`'s own frozen definition — *"manages the platform's day-to-day operation"* — plausibly includes reviewing security-relevant activity as part of that operational role, whereas renaming the Organization itself is a rarer, more foundational action better reserved for `Owner` specifically. This is a judgment call, not a mechanical derivation, and is flagged as such — see [`adr/ADR-004-audit-and-org-settings-restricted-access.md`](./adr/ADR-004-audit-and-org-settings-restricted-access.md) for the full reasoning and the alternative that was considered and rejected.

---

## 6. Permission Matrix

| Action | Owner | Admin | Member |
|--------|:-----:|:-----:|:------:|
| `GET /organization` | ✅ | ✅ | ✅ |
| `PATCH /organization` | ✅ | ❌ | ❌ |
| `GET /me`, `PATCH /me`, `POST /me/change-password` | ✅ (own) | ✅ (own) | ✅ (own) |
| `GET /audit-logs` (list, single) | ✅ | ✅ | ❌ |
| `GET /security-logs` | ✅ | ✅ | ❌ |

---

## 7. Organization Scoping

Unchanged pattern: `audit_logs` queries are always scoped to `organization_id`, derived from the `AuthenticatedIdentity` — no Organization ever sees another's audit trail. `GET /organization` and `PATCH /organization` have no cross-tenant surface at all (same reasoning as Dashboard's `GET /dashboard` — the endpoint is singular and always reflects the caller's own Organization, with no target-resource parameter to scope against).

---

## 8. Summary

```text
Authorization — Stage 7

Organization Settings (write)  → Owner only (foundational-action precedent)
Profile (own)                    → every Role, self-scoped, no Role gate needed
Audit / Security Logs             → Owner + Admin only (new-information + governance-
                                       data precedent) — the first asymmetric,
                                       Member-excluded read decision in this series
```
