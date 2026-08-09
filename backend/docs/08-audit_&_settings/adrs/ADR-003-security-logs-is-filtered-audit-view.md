# ADR-003: "Security Logs" Is a Filtered View Over `audit_logs`, Not a Separate Table or Module

| | |
|---|---|
| **Status** | ✅ Accepted (Engineering Default — Resolves a Genuine Gap) |
| **Scope** | Audit Module |
| **Affects** | `GET /security-logs`, and whether a second write path or table exists |

---

## Context

[`08-sprint-roadmap.md`](../../00-backend_architecture/00-backend_architecture/08-sprint-roadmap.md) §9 lists `Security Logs` as the fourth, final item in this Sprint's chain (`Audit → Organization Settings → Profile → Security Logs`), with no further elaboration anywhere in any frozen document — no schema, no API reference, no module-responsibilities entry distinct from `Audit`'s own §9 description.

---

## Decision

"Security Logs" is implemented as a fixed, named filter (`action IN (...)`, per [`06-security-logs.md`](../06-security-logs.md) §2) applied to the same `AuditRepository::listForOrganization()` query already built for the general Audit Log endpoint. No second table, no second write path, no second module.

---

## Rationale

### Why not treat "Security Logs" as a genuinely distinct concept, given the roadmap lists it as its own line item?
Because every other line item in that same roadmap list turned out, on inspection, to resolve to something already covered elsewhere — `Organization Settings` resolved to the already-named Organization module's own responsibility ([`01-overview.md`](../01-overview.md) §3), and this series' own [`docs/backend/dashboard/adr/ADR-001`](../../07-dashboard/adr/ADR-001-dashboard-scope-is-single-aggregation-endpoint.md) already established the precedent that this roadmap's prose outlines list *conceptual* areas of work, not always literally distinct new artifacts. Reading "Security Logs" the same way — as the Audit module's own data, viewed through a security-specific lens — is consistent with that established precedent, not a new kind of judgment call.

### What would a separate `security_logs` table actually buy, that a filtered view doesn't?
Nothing identifiable. A separate table would require either (a) writing every security-relevant event twice — once to `audit_logs`, once to `security_logs` — introducing exactly the kind of duplicated-write-path risk (the two could drift out of sync) this series has avoided everywhere else, or (b) some non-obvious data living in `security_logs` that doesn't also belong in `audit_logs`, which no frozen document suggests exists.

### Is the specific list of "security" actions in `06-security-logs.md` §2 itself a business rule, or a default?
An engineering default, explicitly — same flagging discipline as Alert's severity thresholds (ADR-001 in that module) and this Sprint's own audit-scope decision (ADR-002). If a real security-review workflow later reveals a different, more precise list is needed (e.g., including failed login attempts specifically, which V1's scope doesn't yet track as a distinct event), this ADR is the place to revise it — a change to a filter list, not a schema change.

---

## Rejected Alternatives

| Alternative | Reason for Rejection |
|-------------|------------------------|
| A separate `security_logs` table with its own write path | Duplicates data, risks drift between two logs of overlapping events, adds complexity with no identified benefit |
| A separate `Security` module, distinct from `Audit` | Not supported by `04-module-responsibilities.md`'s "Final Module Count: 8," which names `Audit` but no separate Security module — inventing a ninth module contradicts an explicit, frozen module count |
| No distinction at all — treat "Security Logs" as a UI-only relabeling of the exact same, unfiltered Audit Log | Discards the roadmap's own signal that these are meant to answer a narrower question; a `GET /security-logs` endpoint with zero actual filtering would be a pointless duplicate route |

---

## Consequences

- ✅ Zero duplicated write paths — every audited event is written exactly once, regardless of whether it's later surfaced via the general or the security-filtered view.
- ✅ The "Security" action list is a small, isolated, easily-revised constant — consistent with how every other genuinely-unspecified default has been handled throughout this series.
- ⚠️ If "Security Logs" was actually meant to include data this design doesn't capture at all (e.g., failed login attempts, which aren't currently a tracked event anywhere in this Sprint's scope), that's a real, separate gap this ADR does not claim to close — flagged here rather than silently assumed away.
