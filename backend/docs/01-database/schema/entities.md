# Entities (Schema Reference)

> This is the main practical reference file. Every table is fully described: its purpose, every column and why it exists, its rules, and its final shape.
> For constraints in detail → [`constraints.md`](./constraints.md). For indexes → [`indexes.md`](./indexes.md). For enums → [`enums.md`](./enums.md).

---

## Overview of the Seven Tables

```text
organizations → users
          → agents → api_keys
                   → observations → predictions → alerts
```

| # | Table | Type | Depends on |
|---|-------|------|-----------|
| 1 | `organizations` | Structured | — (Root) |
| 2 | `users` | Structured | `organizations` |
| 3 | `agents` | Structured | `organizations` |
| 4 | `api_keys` | Structured | `agents` |
| 5 | `observations` | Hybrid (JSONB) | `organizations`, `agents` |
| 6 | `predictions` | Hybrid (JSONB) | `observations` |
| 7 | `alerts` | Structured | `predictions` |

---

## 1. `organizations`

### Purpose
The root entity of the entire system. Every tenant on the platform is a Organization. The real client registers a organization, not a personal account (`Register Organization`, not `Register User`).

### Columns

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| `id` | UUID v7 | ✅ | Primary Key |
| `name` | String | ✅ | Organization name. **Not unique** — two organizations can share the same brand name |
| `slug` | String | ✅ | A unique, URL-safe identifier (e.g., `microsoft`, `google`). Prepared for future use in paths like `sentinelx.ai/microsoft` or a subdomain `microsoft.sentinelx.ai` |
| `status` | Enum | ✅ | `ACTIVE` \| `SUSPENDED` only. No `ARCHIVED` or `PENDING` in V1 |
| `created_at` | Timestamp | ✅ | |
| `updated_at` | Timestamp | ✅ | |

### Key Decisions
- **No `owner_id` column**: the organization is an independent entity, and the user is an independent entity. The relationship is "User belongs to Organization," not "Organization belongs to Owner" — this opens the door to future support for ownership transfer, multiple owners, and member invitations, without redesigning the database.
- **No soft delete**: the `SUSPENDED` status is sufficient to represent a organization that has stopped using the platform without losing data.

### Final Shape
```text
organizations
──────────────────────
id (UUID v7)         PK
name
slug                  UNIQUE
status                ACTIVE | SUSPENDED
created_at
updated_at
```

---

## 2. `users`

### Purpose
The people belonging to a organization. A User is **not** the client — it's simply a person who belongs to a organization (Ahmed works for Microsoft — not Ahmed owns the platform).

### Columns

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| `id` | UUID v7 | ✅ | Primary Key |
| `organization_id` | UUID (FK) | ✅ | References `organizations.id` |
| `full_name` | String | ✅ | |
| `email` | String | ✅ | **Globally unique** (not scoped per organization) — in V1, one user = one account |
| `password_hash` | String | ✅ | Hash only, never plain text |
| `role` | Enum | ✅ | `OWNER` \| `ADMIN` \| `MEMBER`. No `VIEWER` in V1 — to avoid over-engineering before building a full RBAC system. See [`backend-architecture/adr/ADR-002-human-identity-baseline-update.md`](../../00-backend_architecture/adr/ADR-002-human-identity-baseline-update.md) |
| `status` | Enum | ✅ | `ACTIVE` \| `DISABLED` |
| `last_login_at` | Timestamp | ❌ Optional | Last login timestamp |
| `email_verified_at` | Timestamp | ❌ Optional (Nullable) | `NULL` = email not yet verified. Set once, when verification succeeds via a signed URL. **Deliberately independent of `status`** — email verification is tracked here, never by adding a status value. See [`../../02-auth/adr/ADR-005-email-verified-at-column.md`](../../02-auth/adr/ADR-005-email-verified-at-column.md) |
| `created_at` | Timestamp | ✅ | |
| `updated_at` | Timestamp | ✅ | |

### Key Decisions
- `email` is globally unique, not scoped per organization, because a person likely uses the same email regardless of the organization. Supporting a user belonging to more than one organization (if ever needed) will be solved via a separate Membership layer, not by relaxing the unique constraint.
- No `deleted_at` — `DISABLED` is the state that represents a user no longer active.
- `role` includes `ADMIN` alongside `OWNER`/`MEMBER`, per the Backend Architecture Cross-Review (`ADR-002-human-identity-baseline-update.md`): the Role model is kept future-proofed for Team Management even though V1 ships single-Owner Organizations.
- `email_verified_at` is **fully independent** of `status` — a user can be `ACTIVE` and unverified at the same time (during registration), and `DISABLED` never implies "unverified." The two columns are unrelated.

### Final Shape
```text
users
──────────────────────
id (UUID v7)           PK
organization_id              FK → organizations.id
full_name
email                    UNIQUE (Global)
password_hash
role                     OWNER | ADMIN | MEMBER
status                   ACTIVE | DISABLED
last_login_at
email_verified_at        Nullable — ADR-005 (auth)
created_at
updated_at
```

---

## 3. `agents`

### Purpose
**The Agent is the real client of the platform** — the human (User) is merely an observer. An Agent here is a broader concept than a "name" or a "bot": it's an **Identity + Security Principal** — it has an independent identity and proves it to the system using an API Key. Conceptually closer to a "Service Account" than a regular "User."

### Columns

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| `id` | UUID v7 | ✅ | Primary Key |
| `organization_id` | UUID (FK) | ✅ | References `organizations.id` |
| `name` | String | ✅ | Human-readable name (e.g., "Support Agent") |
| `framework` | String | ✅ | The framework used to build the agent, sourced from ASES Context (e.g., `CrewAI`, `LangGraph`, `OpenAI Agents SDK`, `AutoGen`). Used later in Dashboard, Analytics, CVE Correlation |
| `framework_version` | String | ❌ Optional | e.g., `1.2.0` |
| `description` | Text | ❌ Optional | Useful for organizations with dozens of agents |
| `status` | Enum | ✅ | `ACTIVE` \| `ARCHIVED` only — no `DISABLED` because the real lifecycle is the "Archive Agent" business action |
| `last_seen_at` | Timestamp | ❌ Optional | Auto-updates every time the agent sends an Observation. Used to display "Last Seen: 2 minutes ago" in the Dashboard |
| `created_at` | Timestamp | ✅ | |
| `updated_at` | Timestamp | ✅ | |

### Key Decisions
- **No API Key column here**: the API Key has a completely independent lifecycle (Rotate, Revoke, Created, Last Used) and therefore lives in a separate table. See [`decisions/adr-004-api-key-strategy.md`](../decisions/adr-004-api-key-strategy.md).
- `name` is unique **within the organization only**, not globally (`UNIQUE(organization_id, name)`).

### Final Shape
```text
agents
──────────────────────
id (UUID v7)              PK
organization_id                 FK → organizations.id
name
framework
framework_version
description
status                     ACTIVE | ARCHIVED
last_seen_at
created_at
updated_at
```

---

## 4. `api_keys`

### Purpose
An independent credential representing the Agent's security identity when connecting to the platform. A separate table from `agents` because credentials have a completely different lifecycle than the entity itself (rotation, revocation, audit).

### Columns

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| `id` | UUID v7 | ✅ | Primary Key |
| `agent_id` | UUID (FK) | ✅ | References `agents.id` |
| `key_prefix` | String | ✅ | A visible portion shown to the user only (e.g., `sk_live_ab12`) — **not** the real key |
| `key_hash` | String | ✅ | The real key is **never** stored — only its hash, like a password |
| `status` | Enum | ✅ | `ACTIVE` \| `REVOKED` |
| `last_used_at` | Timestamp | ❌ Optional | The key's last actual use |
| `expires_at` | Timestamp | ❌ Optional | Added from V1 as a precaution, even though expiration isn't actively used yet |
| `created_at` | Timestamp | ✅ | |
| `updated_at` | Timestamp | ✅ | Present for consistency with the rest of the tables, even though the record is nearly immutable in practice |

### Key Decisions
- The relationship `Agent (1) → API Keys (∞)` — **not** One-to-One — to support key rotation without losing the history of old keys (kept as `REVOKED` for audit purposes).
- **Business Rule (not a database constraint):** only one active (`ACTIVE`) key per Agent at any time. The database technically allows more than one record, but the application layer is responsible for enforcing this rule — making rotation possible with zero downtime.

See full details in [`decisions/adr-004-api-key-strategy.md`](../decisions/adr-004-api-key-strategy.md).

### Final Shape
```text
api_keys
──────────────────────
id (UUID v7)             PK
agent_id                  FK → agents.id
key_prefix
key_hash                  UNIQUE
status                    ACTIVE | REVOKED
last_used_at
expires_at
created_at
updated_at
```

---

## 5. `observations`

### Purpose
**The most important table in the project.** An Observation is not a log — it's a **formal security document**. This is why the full ASES JSON is stored exactly as it arrived from the SDK, without any decomposition.

### Columns

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| `id` | UUID v7 | ✅ | Primary Key |
| `organization_id` | UUID (FK) | ✅ | **Deliberate denormalization** — even though it could be derived from `agent_id`. See the architectural decision below this table |
| `agent_id` | UUID (FK) | ✅ | References `agents.id` |
| `analysis_status` | Enum | ✅ | `PENDING` \| `PROCESSING` \| `COMPLETED` \| `FAILED` — analysis is asynchronous, and this column reflects the current stage of the full cycle |
| `raw_ases_json` | JSONB | ✅ | **The heart of the table** — the full ASES JSON exactly as it arrived from the SDK. This is the Source of Truth. It is never broken up into Events tables |
| `received_at` | Timestamp | ✅ | The actual time the SDK's payload arrived (logically distinct from `created_at`, even if they coincide in practice) |
| `processing_started_at` | Timestamp | ❌ Optional | When ML started processing the Observation |
| `processed_at` | Timestamp | ❌ Optional | When processing finished |
| `created_at` | Timestamp | ✅ | When the record itself was created |
| `updated_at` | Timestamp | ✅ | |

### Key Decisions
- **No `sdk_version` column**: it already exists inside `raw_ases_json`, and it's never queried directly — so there's no need to extract it.
- **No decomposition into Events**: an old, confirmed decision from the start of the project — Events remain part of the full JSON.
- **No `prediction_id` column**: the reverse relationship is cleaner — the Prediction points to the Observation, keeping the Observation independent even if ML fails.

### The Most Important Architectural Decision: Why does `organization_id` exist despite the duplication?

This is **calculated denormalization**, not random duplication:
- Nearly every query in the Dashboard starts with `organization_id`.
- It avoids a `JOIN` against the `agents` table in most read operations.
- It isolates the Observation from any future changes to the Agent's data.
- It makes the core queries faster and simpler.

The general rule: *Normalize by default... Denormalize only when it clearly improves real business queries.*

### Final Shape
```text
observations
──────────────────────────
id (UUID v7)                 PK
organization_id                    FK → organizations.id
agent_id                      FK → agents.id
analysis_status                PENDING | PROCESSING | COMPLETED | FAILED
raw_ases_json (JSONB)
received_at
processing_started_at
processed_at
created_at
updated_at
```

---

## 6. `predictions`

### Purpose
The result of ML's analysis of a given Observation. Once stored, it becomes **part of the platform's audit history** — even if the model changes later, we can go back and see how the old model saw this event.

### Columns

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| `id` | UUID v7 | ✅ | Primary Key |
| `observation_id` | UUID (FK) | ✅ | **Unique** — a One-to-One relationship with the Observation |
| `verdict` | Enum | ✅ | `SAFE` \| `SUSPICIOUS` \| `MALICIOUS` — a direct column (not inside JSON) because it's used heavily in the Dashboard |
| `confidence` | Decimal | ✅ | A value between `0` and `1`, e.g., `0.91` |
| `risk_score` | Integer | ✅ | A value between `0` and `100`, extracted as a column for Dashboard sorting and filtering |
| `summary` | Text | ✅ | The short, condensed summary of the result |
| `model_version` | String | ✅ | The version of the model that produced this analysis — very important for historical lookups |
| `prediction_json` | JSONB | ✅ | The full ML response: Evidence, Reasons, Models, Datasets, MITRE, OWASP, etc. |
| `analyzed_at` | Timestamp | ✅ | When analysis finished |
| `created_at` | Timestamp | ✅ | |
| `updated_at` | Timestamp | ✅ | |

### Key Decisions
- The relationship `Observation (1) → Prediction (0..1)` — **not** 1..1: right when the Observation arrives, its state is `PENDING` with no Prediction yet.
- `risk_score` is kept separate from `verdict` (both exist) because the Dashboard needs both independently — the numeric value for sorting, and the text for quick display.

### Final Shape
```text
predictions
──────────────────────────
id (UUID v7)                  PK
observation_id                 FK → observations.id, UNIQUE
verdict                        SAFE | SUSPICIOUS | MALICIOUS
confidence                     Decimal (0..1)
risk_score                     Integer (0..100)
summary
model_version
prediction_json (JSONB)
analyzed_at
created_at
updated_at
```

---

## 7. `alerts`

### Purpose
The final operational decision (Business Event) resulting from the platform's policy based on the Prediction's outcome. An Alert is **not** a Prediction — Prediction is analysis, Alert is a business event with its own lifecycle.

### Columns

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| `id` | UUID v7 | ✅ | Primary Key |
| `prediction_id` | UUID (FK) | ✅ | **Unique** — a single Prediction produces at most one Alert |
| `severity` | Enum | ✅ | `LOW` \| `MEDIUM` \| `HIGH` \| `CRITICAL` — deliberately separate from `risk_score`, because users think in colors, not raw numbers |
| `status` | Enum | ✅ | `OPEN` \| `ACKNOWLEDGED` \| `RESOLVED` — an operational lifecycle. No `ARCHIVED` here because archiving is a storage strategy, not a business state |
| `acknowledged_at` | Timestamp | ❌ Nullable | |
| `resolved_at` | Timestamp | ❌ Nullable | |
| `created_at` | Timestamp | ✅ | |
| `updated_at` | Timestamp | ✅ | |

### Key Decisions
- The relationship `Prediction (1) → Alert (0..1)` — because a `SAFE` verdict will never produce an Alert.
- **Foreign Key points to the Prediction, not directly to the Observation**: logically, the platform's policy is what decides to create the Alert based on the analysis result — but at the data relationship level, the Alert links directly to the Prediction it was based on, which maintains the separation of concerns.

### Final Shape
```text
alerts
──────────────────────────
id (UUID v7)               PK
prediction_id                FK → predictions.id, UNIQUE
severity                     LOW | MEDIUM | HIGH | CRITICAL
status                        OPEN | ACKNOWLEDGED | RESOLVED
acknowledged_at
resolved_at
created_at
updated_at
```

---

## Full Table Tree Summary

```text
organizations
    │
    ├── users
    │
    └── agents
            │
            ├── api_keys
            │
            └── observations
                    │
                    └── predictions
                            │
                            └── alerts
```

> Note: `observations` is also linked directly to `organizations` (deliberate denormalization), in addition to its link to `agents`.
