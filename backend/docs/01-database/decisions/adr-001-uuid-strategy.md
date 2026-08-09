# ADR-001: UUID v7 as the Sole Primary Key Strategy for All Tables

| | |
|---|---|
| **Status** | ✅ Accepted (Frozen) |
| **Session** | Session 3 — Database Standards |
| **Affects** | All seven tables, without exception |

---

## Context

Every table in the SentinelX database needs a Primary Key. The options considered were:

1. `Auto Increment Integer` (traditional)
2. `UUID v4` (fully random)
3. `UUID v7` (time-ordered)

---

## Decision

**Every Primary Key in the system is `UUID v7`.** No table uses an auto-increment integer.

```text
id UUID v7 PRIMARY KEY
```

---

## Rationale

### Why UUID instead of Auto Increment?
The platform is **public-facing** (SDK, public REST API). If we used sequential integers, IDs would be trivially guessable:

```text
/organization/1
/organization/2
/organization/3
```

Any external party could infer the platform's scale or access records sequentially. UUIDs solve this security problem entirely.

### Why UUID v7 specifically, and not v4?

| Property | UUID v4 | UUID v7 |
|----------|---------|---------|
| Time ordering | ❌ Fully random | ✅ Ordered by creation time |
| Index performance | ❌ Causes heavy fragmentation in B-Tree indexes | ✅ Significantly reduces fragmentation |
| PostgreSQL fit | Moderate | ✅ Excellent |
| Suitable for future distributed systems | ✅ | ✅ (and better, due to time ordering) |

A fully random UUID v4 means every new insert lands at a random point inside the index's B-Tree, increasing maintenance and read costs as the table grows. UUID v7 solves this because it retains an approximate time order, so new values are inserted near the end of the tree — behaving much like an auto-increment column — while still being impossible to guess.

---

## Rejected Alternatives

| Alternative | Reason for Rejection |
|-------------|------------------------|
| Auto Increment Integer | Allows ID guessing — unacceptable for a public platform |
| UUID v4 | Fully random ordering → measurably worse index performance as data grows |

---

## Consequences

- ✅ Natural protection against ID enumeration attacks.
- ✅ Noticeably better index performance compared to UUID v4.
- ✅ Ready for future expansion toward microservices or distributed systems without changing the key strategy.
- ⚠️ Slightly larger storage footprint compared to integers (16 bytes vs. 4-8 bytes) — an acceptable tradeoff given the security and architectural benefits.
