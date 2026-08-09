# 01 — Overview: Three Modules, One Sprint

> Extends [`backend-architecture/03-system-modules.md`](../00-backend_architecture/00-backend_architecture/03-system-modules.md) §3–4 and [`04-module-responsibilities.md`](../00-backend_architecture/00-backend_architecture/04-module-responsibilities.md) §3 and §9.

---

## 1. Why This Sprint Is Different From Every One Before It

Sprints 2 through 6 each built out exactly one already-scoped module. This Sprint builds out **three** — `Audit`, `Organization`, and a small extension to `Authentication` — because the frozen [`08-sprint-roadmap.md`](../00-backend_architecture/00-backend_architecture/08-sprint-roadmap.md) §9 groups them into a single Sprint, and because none of the three had a complete implementation-ready design before this folder.

---

## 2. Module 1 — Audit

Per [`04-module-responsibilities.md`](../00-backend_architecture/00-backend_architecture/04-module-responsibilities.md) §9:

```text
Owns: Audit Events
Responsible For: Recording events, Searching events, Displaying events
Never: Intervenes in any business logic
```

Per [`05-module-dependencies.md`](../00-backend_architecture/00-backend_architecture/05-module-dependencies.md) §16: *"Audit only records. If Audit fails, `Login`, `Observation`, and `Alert` must all continue to function regardless. It is a Recorder, nothing more."* Every module in the backend — all seven before it — reports into Audit. Audit reports back to none of them. See [`03-audit-logging.md`](./03-audit-logging.md).

---

## 3. Module 2 — Organization

Per [`04-module-responsibilities.md`](../00-backend_architecture/00-backend_architecture/04-module-responsibilities.md) §3:

```text
Owns: Organization, Organization Profile, Organization Settings
Responsible For: Create Organization, Update Organization details,
                    Manage Organization settings, Identify the Organization's Owner
Does NOT Own: Login, API Keys, Agents
```

Per [`03-system-modules.md`](../00-backend_architecture/00-backend_architecture/03-system-modules.md) §3: *"Is There a Settings Module? **No**... `Settings` is not a Business Capability; it's an extension of `Organization`."* This resolves the Sprint roadmap's "Organization Settings" item directly — it is this module's own `Update Organization details` / `Manage Organization settings` responsibility, nothing more exotic.

**This module was never given its own documentation folder before now**, even though it has existed conceptually since Stage 1 (Organization Registration already works, per the MVP's own Definition of Done). See [`04-organization-settings.md`](./04-organization-settings.md) §5 for how this Sprint reconciles that.

---

## 4. Module 3 (Extension) — Authentication's Profile Responsibility

Per [`04-module-responsibilities.md`](../00-backend_architecture/00-backend_architecture/04-module-responsibilities.md) §2, Authentication already owns `Users` and already lists `Current User (/me)` among its responsibilities — this was built in Stage 1 (`GET /me`, per [`docs/backend/agent/An_example_of_implementing_authentication...`](../02-auth) precedent). What Stage 1 did **not** build is the write side: updating one's own name, or changing one's own password. This Sprint adds exactly that, as a small extension to the already-frozen Authentication module — not a new module, not a new folder of its own.

---

## 5. What "Security Logs" Turns Out to Mean

No frozen document defines this as a distinct concept from Audit. This folder resolves it as a **filtered view over the same Audit Logs data** — see [`06-security-logs.md`](./06-security-logs.md) and [`adr/ADR-003-security-logs-is-filtered-audit-view.md`](./adr/ADR-003-security-logs-is-filtered-audit-view.md).

---

## 6. Module Boundary Recap

```text
Audit Module                          Organization Module               Authentication Module
────────────────────                  ──────────────────────             (extended, not new)
✔ Audit Log entries                   ✔ Organization row                 ✔ Users (already owned)
✔ Recording/searching/displaying       ✔ Organization Profile/Settings     ✔ Profile self-service (NEW)
✘ Never intervenes in business logic    ✘ Does NOT own Login/API Keys/       ✘ Still does not own
                                            Agents                             Organizations (per
                                                                                 module-responsibilities §2)
```

Dependency direction — per [`05-module-dependencies.md`](../00-backend_architecture/00-backend_architecture/05-module-dependencies.md) §10 (Audit) and the "Complete Picture" diagram (Organization as root):

```text
Every other module
    │
    ▼ (fire-and-forget events only — see 03-audit-logging.md)
Audit

Authentication (Identity submodule)
    ↓
Organization   (root — nothing depends on Organization the way Organization
                  depends on nothing; Organization is the base of the entire chain)
```

**Audit depends on nothing and nothing depends on Audit** — it is the one module that sits entirely outside the main dependency chain, purely as a passive recorder. **Organization sits beneath everything** — every other module's Organization-scoping ultimately traces back to this module's own root entity, even though most modules never call into Organization directly (they receive `organization_id` via the `AuthenticatedIdentity`, exactly as established since Stage 2).

---

## 7. Session Summary

```text
Stage 7 — Overview

Audit           → new module, new table (flagged explicitly), passive recorder
Organization     → new folder for an old, already-partially-built module
Authentication     → small, additive Profile extension

Golden Rule (unchanged, restated a final time)
✔ Every module still only ever reaches upward through narrow, explicit contracts
  or fire-and-forget events — never a direct table reach-across, even here, at
  the very end of the series.
```
