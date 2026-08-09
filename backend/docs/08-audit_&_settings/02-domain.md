# 02 — Domain: The New `audit_logs` Table

> The only file in this entire documentation series that introduces a table beyond the frozen 7. See [`adr/ADR-001-new-audit-logs-table.md`](./adr/ADR-001-new-audit-logs-table.md) for the full justification — this file only specifies its shape.

---

## 1. The `audit_logs` Table

```text
audit_logs
──────────────────────────
id                  UUID v7, PK
organization_id      FK → organizations.id, required
actor_type            USER | SYSTEM, required
actor_id                FK → users.id, nullable (nullable exactly when actor_type = SYSTEM)
action                    String, required (e.g. "agent.created", "alert.resolved",
                            "organization.updated", "user.password_changed")
resource_type              String, required (e.g. "Agent", "Alert", "Organization", "User")
resource_id                  UUID, nullable (nullable for actions with no single
                                subject resource — none currently anticipated, but the
                                schema allows it rather than forcing a placeholder)
metadata                       JSONB, nullable (small, structured context — e.g.
                                  { "previous_name": "...", "new_name": "..." } for a
                                  rename; never large payloads, never raw_ases_json-style
                                  documents)
created_at                       timestamp, required
```

**No `updated_at`.** An audit log entry is written once and never modified — the strongest possible immutability guarantee in this entire schema, stronger even than Observation's (Stage 3), because an audit trail that could be edited after the fact would defeat its entire purpose.

`ON DELETE RESTRICT` on `organization_id` and `actor_id` (when present) — matching the platform-wide convention already established for every other table.

---

## 2. Why `actor_type` Has Only Two Values, Not Three

An earlier instinct might reach for `USER | AGENT | SYSTEM` — but per [`03-audit-logging.md`](./03-audit-logging.md) §3 and [`adr/ADR-002-audit-scoped-to-human-initiated-actions.md`](./adr/ADR-002-audit-scoped-to-human-initiated-actions.md), Agent actions (submitting Observations) are deliberately **not** audited in V1 — they're already fully recorded, immutably, in the `observations` table itself, and auditing every single Observation submission again in a second table would be pure duplication at very high volume. `SYSTEM` exists for the rare case of a platform-initiated action with no Human actor (none is currently planned for V1, but the column accommodates it without a future migration).

---

## 3. No New Columns Needed on Any Existing Table

This Sprint's other two responsibilities — Organization Settings and Profile — require **zero schema changes**. Every field they touch already exists:

```text
organizations.name    ← already exists, editable (Stage 7 adds the endpoint, not the column)
organizations.slug    ← already exists, but NOT made editable in V1 — see
                          docs/backend/database/entities.md's own framing of slug as
                          "prepared for future use in paths/subdomains"; changing it
                          post-creation has real implications (broken links, DNS)
                          no frozen document addresses, so this Sprint leaves it
                          read-only rather than inventing a redirect/migration strategy
users.full_name        ← already exists, editable
users.password_hash      ← already exists, updatable via a dedicated change-password
                             flow (see docs/backend/audit-settings/05-profile.md)
```

---

## 4. Domain Invariants

```text
1. An audit_logs row is never updated or deleted, by anyone, ever — not even by an
   Owner, not even for a genuine mistake in the recorded data. If an audited action
   itself was wrong (e.g., an Agent was archived by mistake), the correction is a NEW
   action (un-archiving, if that existed, or creating a new Agent) — never an edit to
   the historical record of what happened.
2. actor_id is always populated when actor_type = USER, and always null when
   actor_type = SYSTEM — never the reverse, never both null and USER.
3. organization_id is always populated — there is no cross-organization or
   platform-wide audit log visible to any single Organization's users.
4. action strings follow a consistent "resource.verb" naming convention (see
   03-audit-logging.md §4) — enforced by code review / the listener implementations,
   not by a database CHECK constraint (the space of valid actions is expected to grow
   over time as new auditable actions are added across modules).
```
