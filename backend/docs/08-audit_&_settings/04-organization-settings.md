# 04 — Organization Settings

> Builds out [`04-module-responsibilities.md`](../00-backend_architecture/00-backend_architecture/04-module-responsibilities.md) §3 into an implementation-ready design — the Organization module's first real documentation folder in this entire series.

---

## 1. What This Module Owns (Restated)

```text
Organization           (the row itself: name, slug, status)
Organization Profile     (currently: same fields as above — no separate "profile"
                            data exists yet beyond the core row; the distinction in
                            module-responsibilities.md between "Organization" and
                            "Organization Profile" is not reflected in any separate
                            column set in the frozen schema, so this Sprint treats
                            them as one and the same for V1)
Organization Settings      (currently: nothing beyond `name` is actually configurable
                              — no notification preferences, no alert thresholds
                              [ADR-011 explicitly defers those], nothing else exists
                              in the frozen schema to configure)
```

---

## 2. What Can Actually Be Updated in V1

```text
name    ← editable, via PATCH /organization
slug     ← NOT editable in V1 (see 02-domain.md §3 for why)
status    ← NOT editable via this endpoint — SUSPENDED is a platform-level action
              (e.g., non-payment, ToS violation), not a self-service Organization
              setting; no frozen document describes a self-service suspend/reactivate
              flow, so none is built
```

**In V1, "Organization Settings" is, concretely, exactly one field: renaming the Organization.** This is stated plainly rather than implied, because the phrase "Organization Settings" could otherwise suggest a much larger surface (notification preferences, branding, integrations) that simply doesn't exist anywhere in the frozen schema or any other frozen document.

---

## 3. `GET /organization` and `PATCH /organization` — Singular, Not `/organizations/{id}`

Unlike Agent or Observation, there is no list of Organizations to page through from within the product itself — a caller only ever has one Organization (their own, resolved from the JWT). The endpoint is therefore singular and unparameterized, exactly like `GET /me` already is for the current User:

```text
GET /organization     → the caller's own Organization
PATCH /organization    → update the caller's own Organization
```

No `organization_id` path or query parameter exists anywhere on these routes — it is always derived from the `AuthenticatedIdentity`, the same discipline applied consistently since Stage 2.

---

## 4. Create Organization — Already Built, Retroactively Re-Homed

Per [`04-module-responsibilities.md`](../00-backend_architecture/00-backend_architecture/04-module-responsibilities.md) §3, `Create Organization` is this module's responsibility. But per the MVP's own Definition of Done, *"Organization Registration"* already works — it was built in Stage 1, before this module had its own documentation folder.

**This Sprint does not require rebuilding or migrating that already-working registration flow.** What it does require is a verification step: confirm that Organization creation, wherever it currently lives in the Stage-1 codebase, is exposed through (or refactored into) an `Organization\Application\CreateOrganizationAction` that the Authentication module's `RegisterAction` calls into — consistent with the dependency direction already established (`Authentication → Organization`, per [`05-module-dependencies.md`](../00-backend_architecture/00-backend_architecture/05-module-dependencies.md)'s "Complete Picture" diagram, where Identity/Authentication sits beneath Organization).

```text
If Stage 1's RegisterAction already calls into a proper Organization-owned Action:
    → nothing to do here; this Sprint's OrganizationRepository and
      UpdateOrganizationAction simply sit alongside the already-correct
      CreateOrganizationAction

If Stage 1's RegisterAction instead creates the `organizations` row directly,
inline, without going through an Organization-owned Action:
    → this is the natural, low-risk moment to extract that INSERT into a proper
      CreateOrganizationAction inside the Organization module, since this Sprint
      is already building that module's Infrastructure layer from scratch for
      Update. This is a refactor of WHERE the logic lives, not a change to WHAT
      it does — RegisterAction's external behavior (and its own tests) should be
      unaffected.
```

This is flagged explicitly, rather than assumed either way, because this documentation series was never shown the actual Stage-1 implementation — only its own design docs (`docs/backend/agent/...` for authentication). Whoever implements this Sprint should check the real code first.

---

## 5. Domain Invariants

```text
1. Every User belongs to exactly one Organization (unchanged, already frozen since
   Stage 1) — this Sprint doesn't touch that relationship.
2. name has no uniqueness constraint (already frozen — "two organizations can share
   the same brand name," per docs/backend/database/entities.md §1).
3. slug remains globally unique and immutable post-creation, in V1.
4. status transitions (ACTIVE ↔ SUSPENDED) are NOT exposed via any endpoint in this
   Sprint — they remain a platform-operator-only concern, with no documented
   self-service mechanism.
5. STATE-002 (integration audit): a SUSPENDED Organization is now enforced, not
   merely defined — `LoginUserAction` rejects Human login and
   `ValidateApiKeyAction` rejects Agent API Key authentication for any
   Organization whose status is not ACTIVE. The transition mechanism itself
   (who can suspend/reinstate, through what interface) is still deliberately
   out of scope — this is now the same "guarded door, no key yet" state as
   invariant 4 for `UserStatus::Disabled`, not a fully built feature.
```
