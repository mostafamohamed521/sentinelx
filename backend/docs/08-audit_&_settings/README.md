# SentinelX — Audit & Settings Documentation

> **Status:** 🟢 **Active Design — Stage 7 (Final Stage) of the Implementation Order**
> **Depends on (Frozen):** `docs.zip` (Documentation Baseline v2.0), `docs/backend/database/`, `docs/backend/backend-architecture/`
> **Also builds on:** `docs/backend/agent/`, `docs/backend/observation/`, `docs/backend/analysis/`, `docs/backend/alert/`, `docs/backend/dashboard/` (all Baseline v1.0)
> **Owner:** Backend Architecture Team
>
> **This is the most gap-heavy folder in the entire series, and that is stated plainly rather than smoothed over.** Every prior module traced back to a fully-specified frozen contract, with at most one or two genuine gaps to fill (a severity threshold, a Role matrix). This one is different: the frozen documentation names three real modules (`Audit`, `Organization`, and Authentication's `Profile` responsibility) and even names a data concept (`Audit Logs`) that **has no schema anywhere in the frozen database design** — the 7-table schema, confirmed exhaustively across every prior folder in this series, has no 8th table for it. Every decision this gap required is named explicitly in §5 and in this folder's own ADRs, not silently resolved.

---

## 1. Why does this folder exist?

Same reason the six folders before it exist. Not a tutorial — the **Source of Truth** an engineer (human or Claude Code) reads before writing a single line of this Sprint's code.

> **If there is ever a conflict between this folder and `docs.zip`, `docs.zip` wins — except where `docs.zip` itself is silent, which this Sprint hits more than any before it. Those silences are resolved here, explicitly, with reasoning.**

---

## 2. Where This Sits in the Roadmap

```text
Stage 0 — Foundation                     ✅ Done
Stage 1 — Organization + Authentication  ✅ Done
Stage 2 — Agent + API Key submodule      ✅ Done
Stage 3 — Observation Pipeline           ✅ Done
Stage 4 — Analysis (ML Integration)      ✅ Done
Stage 5 — Alert Engine                   ✅ Done
Stage 6 — Dashboard                      ✅ Done
Stage 7 — Audit + Settings               🟢 THIS FOLDER — the final Stage
```

Per [`backend-architecture/08-sprint-roadmap.md`](../00-backend_architecture/00-backend_architecture/08-sprint-roadmap.md) §9: `Audit → Organization Settings → Profile → Security Logs`. **Definition of Done: "the system is ready as a complete MVP."**

---

## 3. The Core Idea in One Sentence

> **This Sprint closes out three separate, already-named-but-never-fully-built responsibilities — the Audit module's event trail, the Organization module's own settings management, and Authentication's Profile self-service — and none of them were fully specified before now.**

---

## 4. Three Modules, One Sprint — Ownership Map

Unlike every prior Sprint, this one is not one module's work. It is the final build-out of **three** already-named-but-previously-undocumented responsibilities:

```text
Audit Module (new folder, new to this series — module-responsibilities.md §9)
    Owns: Audit Events
    Responsible for: Recording, Searching, Displaying events
    Never: Intervenes in business logic

Organization Module (new folder, new to this series — module-responsibilities.md §3)
    Owns: Organization, Organization Profile, Organization Settings
    Responsible for: Create Organization, Update Organization details,
                        Manage Organization settings, Identify the Owner
    Does NOT own: Login, API Keys, Agents

Authentication Module (EXTENDING the already-frozen docs/backend/agent's sibling —
                          docs/backend/observation/... no: extending the ORIGINAL
                          02-auth folder from Stage 1)
    Already owns: Users, "Current User (/me)" — per module-responsibilities.md §2
    This Sprint adds: Update own profile, Change own password
```

Per [`backend-architecture/03-system-modules.md`](../00-backend_architecture/00-backend_architecture/03-system-modules.md) §3: *"Is There a Settings Module? **No** — an explicit decision. `Settings` is not a Business Capability; it's an extension of `Organization`."* This single line resolves what "Organization Settings" in the Sprint roadmap actually means: it is Organization-module work, not a new module.

**"Security Logs"** (the roadmap's fourth item) is resolved in this folder as a filtered view over the same Audit Logs data — see [`06-security-logs.md`](./06-security-logs.md) — not a fifth module or a second table.

---

## 5. The Gaps This Folder Resolves, Named Up Front

```text
Gap 1 — No audit_logs table exists in the frozen 7-table schema, despite
          module-responsibilities.md naming "Audit Events" / "Audit Logs" as
          real, owned data. Resolved in adr/ADR-001-new-audit-logs-table.md —
          the ONLY new table proposed anywhere in this documentation series.

Gap 2 — No frozen document specifies which actions get audited. Resolved in
          adr/ADR-002-audit-scoped-to-human-initiated-actions.md.

Gap 3 — No frozen document defines "Security Logs" as distinct from "Audit."
          Resolved in adr/ADR-003-security-logs-is-filtered-audit-view.md.

Gap 4 — No frozen document provides a Role matrix for Audit, Security Logs,
          Organization Settings, or Profile. Resolved in
          adr/ADR-004-audit-and-org-settings-restricted-access.md — the first
          genuinely ASYMMETRIC Role decision in this entire series (every prior
          gap-filled Role decision landed on "all three Roles equal").

Gap 5 — The Organization module was never given its own documentation folder,
          despite existing conceptually since Stage 1 (Organization Registration
          already works, per the MVP Definition of Done). This folder is also
          where that retroactive documentation debt gets paid — see
          04-organization-settings.md §5.
```

---

## 6. Folder Architecture

```text
08-audit-settings/
│
├── README.md                          ← you are here
│
├── 01-overview.md                     ← The three-module ownership split, restated in detail
├── 02-domain.md                       ← The new audit_logs table; confirms no other new columns needed
├── 03-audit-logging.md                ← The event-fan-in pattern: every module → Audit
├── 04-organization-settings.md        ← Organization module's own CRUD, and the Stage-1 retroactive note
├── 05-profile.md                      ← Authentication's Profile extension
├── 06-security-logs.md                ← Why this is a filtered Audit view, not a new concept
├── 07-authorization.md                ← The asymmetric Role decisions, explained
├── 08-api-contract.md                 ← Every endpoint across all three modules
├── 09-implementation-roadmap.md       ← Build order, Sprint 7 breakdown
│
├── adr/
│   ├── ADR-001-new-audit-logs-table.md
│   ├── ADR-002-audit-scoped-to-human-initiated-actions.md
│   ├── ADR-003-security-logs-is-filtered-audit-view.md
│   └── ADR-004-audit-and-org-settings-restricted-access.md
│
└── diagrams/
    ├── audit-event-fanin.svg
    └── audit-settings-module-map.svg
```

---

## 7. Recommended Reading Order

| # | File | What you'll learn |
|---|------|--------------------|
| 1 | [`01-overview.md`](./01-overview.md) | The three-module split, in full |
| 2 | [`02-domain.md`](./02-domain.md) | The new `audit_logs` table's shape |
| 3 | [`03-audit-logging.md`](./03-audit-logging.md) | How every prior module reports into Audit without depending on it |
| 4 | [`04-organization-settings.md`](./04-organization-settings.md) | Organization module's own responsibilities, plus the Stage-1 retroactive note |
| 5 | [`05-profile.md`](./05-profile.md) | The small Authentication extension |
| 6 | [`06-security-logs.md`](./06-security-logs.md) | Why this isn't a second table |
| 7 | [`07-authorization.md`](./07-authorization.md) | Why this Sprint breaks the "everyone equal" pattern, on purpose |
| 8 | [`08-api-contract.md`](./08-api-contract.md) | Every endpoint, across all three modules |
| 9 | [`09-implementation-roadmap.md`](./09-implementation-roadmap.md) | Build order, mapped to Layers, across three modules at once |
| — | [`adr/`](./adr) | Four ADRs — more than any prior single Sprint, matching the gap density |
| — | [`diagrams/`](./diagrams) | Event fan-in diagram, module-ownership map |

---

## 8. Design Status

```text
Audit & Settings Design
████████████████████████████ 100% (ready for implementation)

Overview                    ✅ Frozen
Domain (new table)          ✅ Frozen
Audit Logging Pattern       ✅ Frozen
Organization Settings       ✅ Frozen
Profile                     ✅ Frozen
Security Logs               ✅ Frozen
Authorization               ✅ Frozen
API Contract                ✅ Frozen
Implementation Roadmap      ✅ Frozen
```

> Once this folder is used to generate code, it becomes frozen too — and, per the roadmap's own Definition of Done, SentinelX's MVP is complete.
