# Naming Conventions

> These naming rules are **frozen** and must apply without exception to any new table or migration.

---

## 1. Table Names

- **Always plural.**
- `snake_case`.

```text
organizations
users
agents
api_keys
observations
predictions
alerts
```

❌ Not allowed: `organization`, `Organization`, `tblOrganization`, `organizationList`

---

## 2. Column Names

- Always `snake_case`.

```text
agent_name
risk_score
created_at
organization_id
```

❌ Not allowed: `AgentName`, `riskScore`, `OrganizationID`

---

## 3. Primary Keys

Every table has an `id` column of type `UUID v7`.

```text
id UUID PRIMARY KEY
```

See [`decisions/adr-001-uuid-strategy.md`](../decisions/adr-001-uuid-strategy.md) for the full reasoning.

---

## 4. Foreign Keys

Always in the format: `<singular_entity_name>_id`

```text
organization_id      → organizations.id
agent_id        → agents.id
observation_id  → observations.id
prediction_id   → predictions.id
```

No exceptions — even if the referenced table's name differs from the entity name.

---

## 5. Timestamps

Every table (with zero exceptions, including `api_keys`) must contain:

```text
created_at
updated_at
```

Plus domain-specific timestamps where needed, all ending in `_at`:

| Table | Additional Columns |
|-------|---------------------|
| `observations` | `received_at`, `processing_started_at`, `processed_at` |
| `predictions` | `analyzed_at` |
| `alerts` | `acknowledged_at`, `resolved_at` |
| `agents` | `last_seen_at` |
| `api_keys` | `last_used_at`, `expires_at` |
| `users` | `last_login_at` |

**Rule:** Any column representing "the time something happened" must end in `_at`. No exceptions.

---

## 6. Enums

Values are stored as **clear UPPERCASE strings**, not numbers.

```text
ACTIVE, ARCHIVED, SUSPENDED, PENDING, OPEN, RESOLVED
```

**Why strings instead of integers?** Clearer to read, easier to debug, and perfectly suited to the current project scale.

The full list of every enum is in [`schema/enums.md`](../schema/enums.md).

---

## 7. JSON Columns

The name always describes the content and is suffixed with `_json`:

```text
raw_ases_json
prediction_json
```

The type is always `JSONB` (PostgreSQL), never plain `JSON` — for better search and indexing performance.

---

## 8. Unique / Composite Constraint Naming

Expressed as:

```text
UNIQUE(column)
UNIQUE(column_a, column_b)   -- Composite
```

Example:
```text
UNIQUE(slug)                     -- organizations
UNIQUE(email)                    -- users
UNIQUE(organization_id, name)         -- agents (Composite)
UNIQUE(key_hash)                 -- api_keys
UNIQUE(observation_id)           -- predictions
UNIQUE(prediction_id)            -- alerts
```

---

## 9. Migration Names

Every entity has its own migration, numbered by dependency order:

```text
001_create_organizations_table
002_create_users_table
003_create_agents_table
004_create_api_keys_table
005_create_observations_table
006_create_predictions_table
007_create_alerts_table
```

See [`implementation/migration-order.md`](../implementation/migration-order.md) for full details.

---

## 10. Quick Reference (Cheat Sheet)

| Element | Rule | Example |
|---------|------|---------|
| Table name | Plural + snake_case | `observations` |
| Column name | snake_case | `risk_score` |
| Primary Key | `id` (UUID v7) | `id` |
| Foreign Key | `<entity>_id` | `agent_id` |
| Timestamp | Ends in `_at` | `received_at` |
| Enum Value | UPPERCASE string | `ACTIVE` |
| JSON Column | `<name>_json` (JSONB) | `raw_ases_json` |
| Migration | Numbered by dependency | `005_create_observations_table` |
