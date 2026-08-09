# Migration Order

> The order here is **mandatory** — each migration depends on the tables that precede it. Running them out of order will fail due to foreign key constraints.

---

## 1. Ordering Principle

Every entity has its own independent migration (not one giant migration creating all tables at once). The order is based on **relationship dependencies**:

```text
Root entity first → then everything that depends on it, in sequence
```

---

## 2. Final Order

| # | Migration | Depends on |
|---|-----------|-----------|
| 1 | `001_create_organizations_table` | — (Root Entity) |
| 2 | `002_create_users_table` | `organizations` |
| 3 | `003_create_agents_table` | `organizations` |
| 4 | `004_create_api_keys_table` | `agents` |
| 5 | `005_create_observations_table` | `organizations`, `agents` |
| 6 | `006_create_predictions_table` | `observations` |
| 7 | `007_create_alerts_table` | `predictions` |

---

## 3. Dependency Chain Visualized

```text
001 organizations
      │
      ├── 002 users            (depends on organizations)
      │
      └── 003 agents            (depends on organizations)
              │
              ├── 004 api_keys   (depends on agents)
              │
              └── 005 observations (depends on organizations + agents)
                      │
                      └── 006 predictions (depends on observations)
                              │
                              └── 007 alerts (depends on predictions)
```

---

## 4. Implementation Notes per Migration

### `001_create_organizations_table`
- `id` UUID v7 PK.
- `slug` UNIQUE NOT NULL.
- `status` Enum (`ACTIVE`, `SUSPENDED`).
- No foreign keys (Root).

### `002_create_users_table`
- `organization_id` FK → `organizations.id`, `ON DELETE RESTRICT`, NOT NULL.
- `email` UNIQUE NOT NULL (global, not composite with `organization_id`).
- `role` Enum (`OWNER`, `ADMIN`, `MEMBER`).
- `email_verified_at` Nullable Timestamp — see [`../../02-auth/adr/ADR-005-email-verified-at-column.md`](../../02-auth/adr/ADR-005-email-verified-at-column.md).

### `003_create_agents_table`
- `organization_id` FK → `organizations.id`, `ON DELETE RESTRICT`, NOT NULL.
- `UNIQUE(organization_id, name)` — Composite Unique.
- `status` Enum (`ACTIVE`, `ARCHIVED`).

### `004_create_api_keys_table`
- `agent_id` FK → `agents.id`, `ON DELETE RESTRICT`, NOT NULL.
- `key_hash` UNIQUE NOT NULL.
- Enable `updated_at` even though the table is nearly immutable (for consistency only).

### `005_create_observations_table`
- `organization_id` FK → `organizations.id`, `ON DELETE RESTRICT`, NOT NULL (denormalized — see [ADR-005](../decisions/adr-005-multi-tenancy.md)).
- `agent_id` FK → `agents.id`, `ON DELETE RESTRICT`, NOT NULL.
- `raw_ases_json` must be of type `JSONB` — **not** plain `JSON`.
- `analysis_status` Enum (`PENDING`, `PROCESSING`, `COMPLETED`, `FAILED`) — remember the default (`DEFAULT 'PENDING'`).

### `006_create_predictions_table`
- `observation_id` FK → `observations.id`, `ON DELETE RESTRICT`, `UNIQUE`, NOT NULL.
- `prediction_json` of type `JSONB`.
- `CHECK (risk_score BETWEEN 0 AND 100)`.
- `CHECK (confidence BETWEEN 0 AND 1)`.

### `007_create_alerts_table`
- `prediction_id` FK → `predictions.id`, `ON DELETE RESTRICT`, `UNIQUE`, NOT NULL.
- `severity` Enum (`LOW`, `MEDIUM`, `HIGH`, `CRITICAL`).
- `status` Enum (`OPEN`, `ACKNOWLEDGED`, `RESOLVED`) — default `OPEN`.

---

## 5. Indexes — Added Within Each Table's Own Migration

No separate migrations are needed for indexes — they're added directly inside the migration that creates each table. See the full list in [`schema/indexes.md`](../schema/indexes.md).

Example (`005_create_observations_table`):
```text
INDEX (agent_id, received_at DESC)
INDEX (organization_id, received_at DESC)
INDEX (analysis_status, received_at ASC)
```

---

## 6. Warning: Never Change the Order

If you need to add a new table in the future, always number it **after** the last existing number (`008_...`), even if the new table is logically "closer" to an older one. The historical order of migrations must never be renumbered in a production environment.
