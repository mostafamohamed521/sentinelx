# SentinelX — Database Documentation

> **Status:** 🔒 **FROZEN** (V1)
> **Last Updated:** After closing the Database Design phase (10 complete sessions)
> **Owner:** Backend / Database Architecture Team

---

## 1. Why does this folder exist?

This documentation is **not** a summary, and it's not an explanation of SQL, Laravel, or PostgreSQL.

It is the **single official Source of Truth** for every decision made in the design of the SentinelX database.

Every table, every column, every index, every constraint, and every relationship is documented here — **along with the reason it exists**, not just its shape.

The goal: any engineer (human or Claude Code) should be able to open this folder and understand, within half an hour, all the thinking that took 10 full design sessions to produce.

> **If there is ever a conflict between the code and this document, this document is the source of truth — unless there is an officially documented update here.**

---

## 2. Purpose of the SentinelX Database

The SentinelX database is a **Multi-Tenant SaaS** platform for managing and monitoring AI Agents from a security standpoint:

```
Organization (Tenant)
    │
    ├── Users            → the humans who monitor
    ├── Agents            → the real client of the platform
    │      └── API Keys   → the Agent's security identity
    └── Observations      → the raw security event (ASES JSON)
            └── Prediction → the ML's opinion on the event
                    └── Alert → the operational decision (Business Event)
```

The golden rule that every design decision is built on:

> **Observation = Fact. Prediction = Opinion. Alert = Business Decision.**

---

## 3. Folder Architecture

```text
backend/docs/01-database/
        │
        ├── README.md                              ← you are here
        │
        ├── architecture/                           ← philosophy (not the tables)
        │   ├── database-overview.md
        │   ├── design-principles.md
        │   └── naming-conventions.md
        │
        ├── schema/                                 ← practical detail per table
        │   ├── entities.md
        │   ├── relationships.md
        │   ├── constraints.md
        │   ├── indexes.md
        │   └── enums.md
        │
        ├── decisions/                              ← ADRs — the pivotal decisions and why
        │   ├── adr-001-uuid-strategy.md
        │   ├── adr-002-soft-delete-strategy.md
        │   ├── adr-003-json-storage.md
        │   ├── adr-004-api-key-strategy.md
        │   └── adr-005-multi-tenancy.md
        │
        ├── diagrams/                                ← visual SVG diagrams
        │   ├── erd.svg
        │   ├── entity-relationships.svg
        │   └── observation-lifecycle.svg
        │
        └── implementation/                          ← translation into actual implementation
            ├── migration-order.md
            └── implementation-notes.md
```

---

## 4. Recommended Reading Order

If this is your first time in this documentation, follow this order:

| # | File | What you'll learn |
|---|------|--------------------|
| 1 | [`architecture/database-overview.md`](./architecture/database-overview.md) | The full story — who uses the database and why |
| 2 | [`architecture/design-principles.md`](./architecture/design-principles.md) | The principles every decision is built on |
| 3 | [`architecture/naming-conventions.md`](./architecture/naming-conventions.md) | Naming rules (must be internalized before any migration) |
| 4 | [`schema/entities.md`](./schema/entities.md) | Every table in detail (columns + purpose of each column) |
| 5 | [`schema/relationships.md`](./schema/relationships.md) | Relationships, cardinality, and delete rules |
| 6 | [`schema/constraints.md`](./schema/constraints.md) | Every constraint and its reason |
| 7 | [`schema/indexes.md`](./schema/indexes.md) | Every index and the query it serves |
| 8 | [`schema/enums.md`](./schema/enums.md) | All enums with their full value sets |
| 9 | [`decisions/`](./decisions) | The five ADRs — the major architectural decisions |
| 10 | [`diagrams/`](./diagrams) | ERD + Entity Relationships + Observation Lifecycle |
| 11 | [`implementation/migration-order.md`](./implementation/migration-order.md) | The actual order for writing migrations |
| 12 | [`implementation/implementation-notes.md`](./implementation/implementation-notes.md) | Implementation notes that must be respected in code |

---

## 5. Design Status

```text
Database Design
████████████████████████████ 100%

Architecture        ✅ Frozen
Entities             ✅ Frozen (7 Entities)
Relationships        ✅ Frozen
Storage Strategy     ✅ Frozen (Hybrid: Structured + JSONB)
Constraints          ✅ Frozen
Indexes              ✅ Frozen (Query-Driven)
Migration Order      ✅ Frozen
```

> **The database design is approved (Frozen) as of the end of Session 10.**
> Any change after this point must be driven by a **new Business Requirement** or a **new product version (V2)**, not just a rethink or optimization.

---

## 6. The Seven Tables (At a Glance)

| Table | Type | Core Purpose |
|-------|------|----------------|
| `organizations` | Structured | The root tenant entity |
| `users` | Structured | Humans belonging to a organization (observers, not clients) |
| `agents` | Structured | The real client — Identity + Security Principal |
| `api_keys` | Structured | An independent credential with its own lifecycle |
| `observations` | Hybrid (JSONB) | The raw security event — Source of Truth |
| `predictions` | Hybrid (JSONB) | The ML's opinion on the Observation |
| `alerts` | Structured | The final operational decision (Business Event) |

Full details for every table are in [`schema/entities.md`](./schema/entities.md).

---

## 7. Project Journey (Broader Context)

```text
Business Idea → Story → Business Requirements → ASES Specification
     → ML Contract → REST API → Database Design (you are here) → Implementation
```

The database design was built **after** the REST API was defined, not before — so that columns and indexes would be based on real usage patterns (Query-Driven Design), not guesswork.

---

## 8. What We Deliberately Did NOT Do (V1 Scope)

To avoid over-engineering, these decisions were **consciously** excluded from V1:

```text
❌ Event Table            ❌ Roles Table
❌ Permissions Table       ❌ Audit Logs Table
❌ API Key Scopes          ❌ Webhooks
❌ Soft Deletes            ❌ Partitioning
❌ Event Sourcing          ❌ CQRS
❌ Full Text Search        ❌ JSON Indexing
❌ Read Replicas           ❌ Materialized Views
```

Every one of these was discussed and rejected for a **specific reason**, not out of ignorance. Details are in [`decisions/`](./decisions).
