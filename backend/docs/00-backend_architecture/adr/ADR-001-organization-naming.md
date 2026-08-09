# ADR-001: `Organization` Replaces `Company` as the Canonical Tenant Name

| | |
|---|---|
| **Status** | ✅ Accepted (Documentation Baseline v2.0) |
| **Conflict Source** | Cross-Review, Conflict 1 |
| **Affects** | Every document, table, and diagram across Database, Authentication, and Backend Architecture documentation |

---

## Context

Two names were used for the same root tenant entity across the project's history:

- Documentation Baseline v1.0 (Domain Model, Database Schema, Security Model, REST API, ADR-002, diagrams — 25 files) uses **`Organization`** / `organizations`.
- The Database Design sessions, the Authentication sessions, and the initial Backend Architecture sessions predominantly use **`Company`** / `companies` / `company_id`.

A Cross-Review against the frozen baseline surfaced this as Conflict 1.

---

## Decision

**`Organization` is adopted as the official name project-wide.** All Backend Architecture documentation uses `Organization` exclusively. `Company` is retired.

---

## Rationale

### The Conflict Is Purely a Naming Drift, Not a Design Disagreement
The project began the Database Design sessions using `Company`. Later, while writing the core Documentation Baseline, it was renamed to `Organization` for accuracy on a multi-tenant SaaS platform. The subsequent Backend Architecture sessions reverted to `Company` by habit, because they were being discussed from the perspective of the older database sessions. The underlying entity, its relationships, and its responsibilities never actually changed — only the label did.

### `Organization` Is More General and More Accurate
A SentinelX customer is not always a "Company" in the strict sense — it could be a university, a startup, a government entity, or a research lab. All of these are Organizations; not all of them are Companies. This also matches the naming convention used by the majority of large SaaS platforms.

---

## Rejected Alternatives

| Alternative | Reason for Rejection |
|-------------|------------------------|
| Keep `Company`, rename the v1.0 baseline to match | Would require touching 25 already-frozen files across Domain Model, Database Schema, Security Model, REST API, and diagrams, for a purely cosmetic reason, while `Organization` is both older (predates the drift) and more accurate |
| Allow both names to coexist contextually (`Company` in Backend docs, `Organization` elsewhere) | Guarantees permanent confusion for anyone cross-referencing documents, and defeats the entire purpose of a single Source of Truth |

---

## Consequences

- ✅ One name, used everywhere: documentation, code, database tables, API payloads.
- ✅ Aligns naturally with the existing, older, and more thorough v1.0 baseline rather than fighting it.
- ⚠️ Any *other* SentinelX documentation still using `Company` (e.g., the earlier Database and Authentication documentation delivered before this resolution) is now **out of sync** and needs a follow-up rename pass — tracked as an open item, not silently applied without visibility.
