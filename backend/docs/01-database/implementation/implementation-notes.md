# Implementation Notes

> Direct, practical notes to keep in mind while writing migrations and models. This file specifically targets whoever is actually implementing the code (including Claude Code).

---

## 1. General Mandatory Rules

```text
✔ Use UUID v7 for every id  (not auto-increment, not UUID v4)
✔ Use JSONB (not JSON) for the columns: raw_ases_json, prediction_json
✔ Never use CASCADE on any foreign key
✔ Never use SET NULL on any foreign key
✔ Use RESTRICT on every foreign key, without exception
✔ Never add a deleted_at column to any table
✔ Hash only for: api_keys.key_hash and users.password_hash — never store plain text
✔ Every table (including api_keys) contains created_at and updated_at
```

---

## 2. Database Engine

**PostgreSQL** is the recommended and adopted engine for the entire design, especially due to:
- Native support for `JSONB` (better performance and indexing than plain `JSON`).
- Excellent native support for `UUID` as a data type.
- Mature, broad support for composite indexes.

---

## 3. Naming — No Exceptions

Review [`architecture/naming-conventions.md`](../architecture/naming-conventions.md) in full before writing any migration. Key points:

- Tables: plural + `snake_case` (`organizations`, `observations`).
- Columns: `snake_case`.
- Foreign Keys: always `<entity>_id`.
- Timestamps: always end in `_at`.
- Enum values: always `UPPERCASE` strings (never numbers).

---

## 4. Business Rules Not Enforced at the Database Level — Must Be Applied in Code

Some business rules are **not automatically enforced** via constraints in the database, and must be explicitly applied at the application (Backend) layer:

| Rule | Where It Must Be Applied |
|------|---------------------------|
| Only one `ACTIVE` key per Agent in `api_keys` | Application layer — when creating a new key, the old one must be revoked (`REVOKED`) first within the same transaction |
| `observations.organization_id` must match the `organization_id` of the Agent linked to that same Observation | Application layer — when inserting a new Observation, derive `organization_id` from the Agent itself; do not rely on an externally supplied value |

---

## 5. Columns Extracted From JSON — Don't Manually Duplicate the Source

Columns like `verdict`, `risk_score`, `confidence`, `summary`, `model_version` in `predictions` are an **extracted copy** of `prediction_json`, kept for fast querying. When inserting a new Prediction:

```text
1. Store the full ML response in prediction_json
2. Extract the required values from it into their dedicated columns
3. Ensure the values always stay consistent (never let them diverge)
```

The same principle doesn't apply literally to `observations`, since all of its non-JSON columns (`analysis_status`, `received_at`...) are metadata about the record's own lifecycle, not values extracted from JSON content.

---

## 6. Migrations — Mandatory Execution Sequence

See [`implementation/migration-order.md`](./migration-order.md) for the full order. Running migrations out of order will fail immediately due to foreign key constraints.

---

## 7. What NOT to Implement in V1 (Reminder)

These decisions are **deliberate**, not a knowledge gap. Do not add any of the following unless there is a new, officially documented business requirement that calls for it:

```text
❌ Event Table                ❌ Roles Table
❌ Permissions Table            ❌ Audit Logs Table
❌ API Key Scopes                ❌ Webhooks
❌ Soft Deletes (deleted_at)      ❌ Partitioning
❌ Event Sourcing                  ❌ CQRS
❌ Full Text Search                  ❌ JSON GIN Indexing
❌ Read Replicas                      ❌ Materialized Views
```

---

## 8. Redis Cache — Important Note

There is exactly one allowed use of Redis in V1: **Dashboard Statistics**. This is **not** part of the database design itself (no migration, no schema) — it's a caching layer entirely separate from the normal query layer. Do not rely on it as a source of truth for any data.

---

## 9. Quick Checklist Before Any Pull Request on the Schema

```text
[ ] Is every Primary Key a UUID v7?
[ ] Does every Foreign Key use ON DELETE RESTRICT?
[ ] Does every Timestamp end in _at?
[ ] Does every Enum use UPPERCASE strings?
[ ] Is every JSON column of type JSONB?
[ ] There is no deleted_at column anywhere?
[ ] Is the new index (if any) tied to a real query and documented in schema/indexes.md?
[ ] Is the decision documented in the appropriate file (entities.md / relationships.md / a new ADR for a major architectural decision)?
```
