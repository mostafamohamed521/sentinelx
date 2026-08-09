# SentinelX — Backend Architecture Documentation

> **Status:** 🔒 **FROZEN** (Documentation Baseline v2.0)
> **Supersedes:** Backend Architecture section of Documentation Baseline v1.0 (frozen 2026-07-24)
> **Last Updated:** After closing the Backend Architecture Design phase (9 sessions) and resolving the Post-Freeze Architecture Alignment (see [`adr/ADR-006-post-freeze-architecture-alignment.md`](./adr/ADR-006-post-freeze-architecture-alignment.md))
> **Owner:** Backend / Software Architecture Team

---

## 1. Why This Document Set Exists

This is not a folder-naming exercise, and it's not a tutorial on Laravel. It is the **single official Source of Truth** for how SentinelX's backend is decomposed into modules, how those modules depend on one another, how each module is structured internally, and in what order the whole thing gets built.

> Architecture is now Frozen. No new features, no architecture changes, no design drift during implementation. **Only implement what was designed.**

The goal: an engineer (human or Claude Code) reads this folder once and then builds the backend in the exact order, with the exact module boundaries, and the exact internal layering designed here — with zero improvisation.

---

## 2. Important: This Supersedes an Earlier, Leaner Baseline

Documentation Baseline v1.0 was frozen on 2026-07-24, before the Authentication design (9 sessions) and this Backend Architecture design (9 sessions) were completed. That earlier freeze described a simpler system: Agent-only authentication, six "Services," and no Organization-internal Users at all.

A full Cross-Review was performed against that baseline, six conflicts were identified, and a decision was made **not to patch v1.0 piecemeal**, but to issue this as **Documentation Baseline v2.0**, with [`adr/ADR-006-post-freeze-architecture-alignment.md`](./adr/ADR-006-post-freeze-architecture-alignment.md) recording exactly what changed and why. The other five ADRs in this folder each resolve one specific conflict in full detail.

**If you are reading any older document that still says `Company`, `Organization Service`, `Prediction Service`, or describes Agent-only authentication — that document is outdated. This folder, plus the resolved Authentication documentation, is current.**

---

## 3. The Core Idea in One Sentence

> **The Feature is the fundamental unit of the project — not the Controller, not the Model, not even the Module. We build outside-in: what can the user do → what features does that require → which module owns each feature → how is that module implemented.**

---

## 4. Folder Architecture

```text
backend/docs/00-backend_architecture/
        │
        ├── README.md                                    ← you are here
        │
        ├── 01-architecture-baseline-review.md             ← Session 1
        ├── 02-product-scope-features.md                    ← Session 2
        ├── 03-system-modules.md                             ← Session 3 (resolved: 8 modules)
        ├── 04-module-responsibilities.md                     ← Session 4
        ├── 05-module-dependencies.md                           ← Session 5
        ├── 06-implementation-layers.md                          ← Session 6 (resolved: Actions, 5 layers)
        ├── 07-implementation-order.md                            ← Session 7
        ├── 08-sprint-roadmap.md                                   ← Session 8
        │
        ├── adr/                                            ← ADRs — including the Post-Freeze Alignment
        │   ├── ADR-001-organization-naming.md
        │   ├── ADR-002-human-identity-baseline-update.md
        │   ├── ADR-003-module-consolidation.md
        │   ├── ADR-004-actions-over-services.md
        │   ├── ADR-005-stateless-jwt-logout.md
        │   └── ADR-006-post-freeze-architecture-alignment.md
        │
        └── diagrams/                                       ← SVG diagrams
            ├── module-map.svg
            ├── dependency-graph.svg
            ├── layered-architecture.svg
            ├── implementation-order.svg
            └── sprint-roadmap.svg
```

Every numbered file is a faithful, synthesized translation of one design session — updated to reflect the resolved conflicts, not a verbatim transcript.

---

## 5. Recommended Reading Order

| # | File | What you'll learn |
|---|------|--------------------|
| 1 | [`01-architecture-baseline-review.md`](./01-architecture-baseline-review.md) | Why architecture is now frozen, and what "Baseline" means going forward |
| 2 | [`02-product-scope-features.md`](./02-product-scope-features.md) | The full Feature Inventory — the official reference for every Sprint, Issue, and API |
| 3 | [`03-system-modules.md`](./03-system-modules.md) | The 8 modules, and why the module boundaries sit exactly where they do |
| 4 | [`04-module-responsibilities.md`](./04-module-responsibilities.md) | Precisely what each module owns — and, just as importantly, what it doesn't |
| 5 | [`05-module-dependencies.md`](./05-module-dependencies.md) | The one-way dependency graph that prevents circular coupling |
| 6 | [`06-implementation-layers.md`](./06-implementation-layers.md) | The internal 5-layer template every module follows, and why Actions replaced Services |
| 7 | [`07-implementation-order.md`](./07-implementation-order.md) | The Stage-by-Stage build order, driven by the dependency graph |
| 8 | [`08-sprint-roadmap.md`](./08-sprint-roadmap.md) | The Sprint plan, and the Epic → Feature → Task → Commit workflow |
| — | [`adr/`](./adr) | The six ADRs — five conflict resolutions plus the meta Post-Freeze Alignment |
| — | [`diagrams/`](./diagrams) | Module map, dependency graph, layered architecture, implementation order, sprint roadmap |

---

## 6. Resolved Module Set (Baseline v2.0)

```text
SentinelX Backend

├── Authentication   (includes Identity + API Keys as internal submodules)
├── Organization
├── Agent
├── Observation
├── Analysis
├── Alert
├── Dashboard
└── Audit
```

**8 modules.** Full detail in [`03-system-modules.md`](./03-system-modules.md) and [`adr/ADR-003-module-consolidation.md`](./adr/ADR-003-module-consolidation.md).

---

## 7. Design Status

```text
Backend Architecture Design
████████████████████████████ 100%

Architecture Baseline Review     ✅ Frozen (v2.0)
Product Scope & Features          ✅ Frozen
System Modules                     ✅ Frozen (8 modules, resolved)
Module Responsibilities             ✅ Frozen
Module Dependencies                  ✅ Frozen
Implementation Layers                 ✅ Frozen (Actions, 5 layers)
Implementation Order                   ✅ Frozen
Sprint Roadmap                          ✅ Frozen
```

> Any change after this point must go through a new ADR — exactly the mechanism that produced this baseline in the first place.

---

## 8. The Golden Rules That Apply Across Every File

```text
No New Ideas.
No New Features.
No Architecture Changes.
Only Implement What Was Designed.
```

```text
Architecture
    ↓
Features
    ↓
Modules
    ↓
Layers
    ↓
Code
```

Never the reverse — never start from folders and controllers and try to reverse-engineer the product from there.
