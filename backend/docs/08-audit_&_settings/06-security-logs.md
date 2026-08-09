# 06 — Security Logs

> Resolves the fourth, least-specified item in [`08-sprint-roadmap.md`](../00-backend_architecture/00-backend_architecture/08-sprint-roadmap.md) §9's list. Full reasoning in [`adr/ADR-003-security-logs-is-filtered-audit-view.md`](./adr/ADR-003-security-logs-is-filtered-audit-view.md) — this file specifies the resulting design.

---

## 1. The Decision

**"Security Logs" is not a new table, not a new module, and not a new concept.** It is a fixed, named subset of the same `audit_logs` table, filtered to a specific category of `action` values that are specifically security-relevant (as opposed to general administrative actions like renaming an Organization).

---

## 2. Which Actions Count as "Security"

```text
user.registered
user.logged_in
user.logged_out
user.password_changed
api_key.generated
api_key.rotated
api_key.revoked
```

**Excluded from "Security Logs" (but still present in the general Audit Log):**
```text
organization.updated
user.profile_updated       (a name change is not security-relevant)
agent.created / updated / archived
alert.acknowledged / resolved
```

This split is this Sprint's own design decision — no frozen document draws this exact line. The reasoning: "Security Logs," as a named UI concept distinct from general "Audit," most plausibly exists to answer questions like *"who logged in, from where, and has anyone rotated a credential recently"* — the classic security-review question set — not *"who renamed an Agent."*

---

## 3. Implementation — a Query Filter, Not a Separate Write Path

```text
Audit\Application\GetSecurityLogsAction
    │
    └── calls the SAME AuditRepository::listForOrganization() method used by the
          general Audit Log endpoint, with a fixed `action IN (...)` filter applied
          (the list in §2 above) — never a separate table, never a separate write
          path, never a second listener recording the same event twice
```

**Every "Security Log" entry is also a regular Audit Log entry.** There is exactly one write path (per [`03-audit-logging.md`](./03-audit-logging.md)); "Security Logs" is purely a read-side, filtered presentation of a subset of that same data.

---

## 4. Why Not a Separate `security_logs` Table

Would duplicate every security-relevant event twice (once in `audit_logs`, once in a hypothetical `security_logs`), for a distinction that's purely about *which actions are interesting to a particular view*, not about any structural difference in the data itself. Per [`adr/ADR-001-new-audit-logs-table.md`](./adr/ADR-001-new-audit-logs-table.md), this Sprint already introduces the one new table this series has needed — introducing a second, near-identical one for a filtering concern would be the wrong kind of complexity, contradicting the project's own stated principle that *"the Simple Solution is better than the Complex Solution, unless the Complex one is actually justified."*

---

## 5. Summary

```text
Security Logs

Data source   → the SAME audit_logs table
Distinction     → a fixed, named filter on `action`, applied at read time
Write path        → identical to general Audit Logging — no duplication
```
