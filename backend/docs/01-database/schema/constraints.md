# Constraints

> Every constraint here exists for a specific, documented reason. There is no "just in case" or "won't hurt" constraint.

---

## 1. `organizations`

| Constraint | Details | Reason |
|-----------|---------|--------|
| `PRIMARY KEY` | `id` | Standard |
| `UNIQUE` | `slug` | Must be unique for use in URLs / future subdomains |
| `NOT NULL` | `name`, `slug`, `status` | Mandatory core fields |

**Note:** `name` is **not** unique — two organizations can share the same brand name.

---

## 2. `users`

| Constraint | Details | Reason |
|-----------|---------|--------|
| `PRIMARY KEY` | `id` | Standard |
| `UNIQUE` | `email` | Globally unique — one user = one account in V1 |
| `FOREIGN KEY` | `organization_id → organizations.id` (`RESTRICT`) | Every user must belong to an existing organization |
| `NOT NULL` | `organization_id`, `full_name`, `email`, `password_hash`, `role`, `status` | Mandatory fields |

---

## 3. `agents`

| Constraint | Details | Reason |
|-----------|---------|--------|
| `PRIMARY KEY` | `id` | Standard |
| `UNIQUE (Composite)` | `(organization_id, name)` | Agent name is unique **within the organization scope only** — allows the same name across different organizations |
| `FOREIGN KEY` | `organization_id → organizations.id` (`RESTRICT`) | Every Agent must belong to an existing organization |
| `NOT NULL` | `organization_id`, `name`, `framework`, `status` | Mandatory fields |

---

## 4. `api_keys`

| Constraint | Details | Reason |
|-----------|---------|--------|
| `PRIMARY KEY` | `id` | Standard |
| `UNIQUE` | `key_hash` | Every hash is inherently unique (a key cannot be duplicated) |
| `FOREIGN KEY` | `agent_id → agents.id` (`RESTRICT`) | Every key must belong to an existing Agent |
| `NOT NULL` | `agent_id`, `key_prefix`, `key_hash`, `status` | Mandatory fields |

**Business Rule (Application Layer, not a Database Constraint):** the count of `ACTIVE` keys per Agent must always be ≤ 1. The database technically allows more than one record (to support rotation without downtime), but the application layer is responsible for enforcing this rule.

---

## 5. `observations`

| Constraint | Details | Reason |
|-----------|---------|--------|
| `PRIMARY KEY` | `id` | Standard |
| `FOREIGN KEY` | `organization_id → organizations.id` (`RESTRICT`) | Denormalized FK for performance |
| `FOREIGN KEY` | `agent_id → agents.id` (`RESTRICT`) | Every Observation must belong to an existing Agent |
| `NOT NULL` | `organization_id`, `agent_id`, `analysis_status`, `raw_ases_json`, `received_at` | Mandatory fields — no Observation can exist without its raw JSON |

---

## 6. `predictions`

| Constraint | Details | Reason |
|-----------|---------|--------|
| `PRIMARY KEY` | `id` | Standard |
| `UNIQUE` | `observation_id` | Enforces the **One-to-One** relationship — a single Observation produces exactly one Prediction in V1 |
| `FOREIGN KEY` | `observation_id → observations.id` (`RESTRICT`) | No Prediction can exist without an original Observation |
| `CHECK` | `0 <= risk_score <= 100` | Value must fall within a logical range |
| `CHECK` | `0 <= confidence <= 1` | Value must fall within a logical range |
| `NOT NULL` | `observation_id`, `verdict`, `confidence`, `risk_score`, `summary`, `model_version`, `prediction_json`, `analyzed_at` | Mandatory fields |

---

## 7. `alerts`

| Constraint | Details | Reason |
|-----------|---------|--------|
| `PRIMARY KEY` | `id` | Standard |
| `UNIQUE` | `prediction_id` | Ensures a single Prediction produces at most one Alert |
| `FOREIGN KEY` | `prediction_id → predictions.id` (`RESTRICT`) | No Alert can exist without an original Prediction |
| `CHECK` | `severity IN ('LOW','MEDIUM','HIGH','CRITICAL')` | Enforces valid enum values |
| `CHECK` | `status IN ('OPEN','ACKNOWLEDGED','RESOLVED')` | Enforces valid enum values |
| `NOT NULL` | `prediction_id`, `severity`, `status` | Mandatory fields |

---

## 8. Unified Delete Strategy (Applies to All Tables)

```text
ON DELETE RESTRICT   ← on every foreign key, no exceptions
```

There is no `CASCADE` and no `SET NULL` on any relationship across the entire database. See the full explanation in [`relationships.md`](./relationships.md#4-delete-strategy).

---

## 9. General Rule for Uniqueness

> **Only make something unique when actually needed — not everything deserves a UNIQUE constraint.**

| Field | Unique? | Reason |
|-------|---------|--------|
| `organizations.name` | ❌ No | Two organizations can share the same brand name |
| `organizations.slug` | ✅ Yes | Used in URLs |
| `users.email` | ✅ Yes (global) | One user = one account |
| `agents.name` | ✅ Yes (within organization only — composite) | Prevents duplicate agent names within the same organization |
| `api_keys.key_hash` | ✅ Yes | Unique by design (result of hashing) |
| `predictions.observation_id` | ✅ Yes | Enforces the 1:1 relationship |
| `alerts.prediction_id` | ✅ Yes | Enforces a maximum of one Alert per Prediction |
