# ADR-006: Post-Freeze Architecture Alignment — Documentation Baseline v2.0

| | |
|---|---|
| **Status** | ✅ Accepted |
| **Conflict Source** | Cross-Review, Conflict 6 (and the umbrella record for Conflicts 1–5) |
| **Affects** | The entire documentation set — this ADR is the formal record that supersedes Documentation Baseline v1.0 |

---

## Context

`docs/12-DOCUMENTATION_FREEZE.md` declared Documentation Baseline v1.0 frozen on 2026-07-24, stating: *"No architectural changes shall be introduced directly into the documentation after this freeze. Any future architectural modification must be documented through a new ADR before being incorporated into the documentation."*

Substantial architecture work continued after that date: the full Authentication design (9 sessions) and the full Backend Architecture design (9 sessions, this document set). A formal Cross-Review comparing this newer work against the v1.0 baseline surfaced five concrete conflicts (naming, missing Human Identity, module set, Services vs. Actions, JWT logout behavior — see ADR-001 through ADR-005), plus this sixth, meta-level question: **how should the freeze policy itself be honored while still incorporating five real architectural updates?**

---

## Decision

**The freeze itself is not broken or edited.** `12-DOCUMENTATION_FREEZE.md` remains an accurate historical record of what was true on 2026-07-24. Instead, this ADR — exactly as the freeze policy itself prescribes — documents the transition, and the documentation set is issued as **Documentation Baseline v2.0**, incorporating ADR-001 through ADR-005 as a single, coherent update rather than five separate incremental patches.

```text
Documentation Baseline v1.0 (Frozen 2026-07-24)
    +
ADR-001 — Organization replaces Company
ADR-002 — Human Identity added; Teams excluded; Invitations deferred
ADR-003 — Identity + API Key consolidated into Authentication
ADR-004 — Actions replace Services in the Application layer
ADR-005 — Stateless JWT, client-side logout confirmed
    =
Documentation Baseline v2.0
```

---

## Rationale

### Why This Happened: A Sequencing Gap, Not a Design Failure
Of the six conflicts identified, four are the natural consequence of continuing to design the system after the freeze point (naming drift, module refinement, terminology tightening, an old generic API spec predating a later deliberate decision). Only one thing was actually a process mistake: **the freeze was issued too early** — before Authentication Design and Backend Architecture Design were complete. Had the freeze been sequenced *after* Product Vision, ML Contract, Authentication Design, and Backend Architecture, none of these five conflicts would have existed at freeze time.

### Why Not Edit v1.0 Directly?
The freeze policy exists precisely to prevent silent architectural drift — quietly rewriting frozen documents defeats the entire purpose of having frozen them. The correct mechanism, as the policy itself states, is an ADR.

### Why a New Baseline Version Instead of Five Standalone Patches?
Patching v1.0 with five independent, uncoordinated changes risks leaving the documentation internally inconsistent at every intermediate step (e.g., some documents renamed to `Organization` before others). Issuing a single, coordinated v2.0 — with all five resolutions applied together and formally recorded — means every document is internally consistent at every point after this ADR is accepted.

---

## Rejected Alternatives

| Alternative | Reason for Rejection |
|-------------|------------------------|
| Silently edit `docs.zip` files to match the newer sessions | Directly violates the freeze policy's own stated change-control mechanism |
| Leave v1.0 as the sole source of truth and discard the newer Authentication/Backend Architecture work | Would leave the platform without Human Identity, without a workable module structure, and without a defensible reason to discard nine sessions each of genuine, validated design work |
| Patch v1.0 incrementally, one conflict at a time, without a versioned baseline | Risks a prolonged period of internal inconsistency across the documentation set while patches are applied one by one |

---

## Consequences

- ✅ The freeze policy's integrity is preserved — v1.0 remains an honest historical record, and this ADR is the audit trail for exactly what changed and why.
- ✅ All five substantive conflicts (ADR-001 through ADR-005) are resolved together, coherently, rather than as scattered patches.
- ✅ This Backend Architecture documentation set is built entirely on the resolved v2.0 terminology and module structure from the outset.
- ⚠️ **Open follow-up work, explicitly not performed as part of this delivery:** `docs.zip`'s own files (Domain Model, Database Schema, Entity Reference, Security Model, `BACKEND_ARCHITECTURE.md`, `AUTH_API.md`, and the D-03 diagram) still reflect v1.0 terminology and scope, and need a dedicated sync pass to actually become v2.0-consistent. The previously delivered Database and Authentication documentation (built before this Cross-Review) also still uses `Company` and specs Team Management/Invitations as V1-scope, and needs the same follow-up treatment.
- 📌 **Process lesson recorded for future phases:** a Documentation Freeze should be issued only after Product Vision, ML Contract, Authentication Design, and Backend Architecture are all complete — not before. This avoids the need for a Post-Freeze Alignment pass like this one in future project phases.
