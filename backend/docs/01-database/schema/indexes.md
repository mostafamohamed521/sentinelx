# Indexes

> Governing principle: **Every Index Must Pay for Itself.**
> No index exists "because it might help" — every index here is tied to a real query from the REST API or the Worker.
> This design is built on **Access Patterns** (real usage patterns), not just data shape.

---

## 1. Methodology

Instead of looking at the tables and asking "which column deserves an index?", we looked at the actual REST API endpoints and asked: **"what queries will this platform run every day?"** Then an index was designed for each real query only.

---

## 2. Final Index List by Table

### `organizations`
```text
PRIMARY KEY (id)
UNIQUE      (slug)
```

### `users`
```text
PRIMARY KEY (id)
UNIQUE      (email)
INDEX       (organization_id)
```

### `agents`
```text
PRIMARY KEY (id)
UNIQUE      (organization_id, name)
INDEX       (organization_id, created_at DESC)
```

### `api_keys`
```text
PRIMARY KEY (id)
UNIQUE      (key_hash)
INDEX       (agent_id)
INDEX       (key_hash, status)
```

### `observations`
```text
PRIMARY KEY (id)
INDEX       (agent_id, received_at DESC)
INDEX       (organization_id, received_at DESC)
INDEX       (analysis_status, received_at ASC)
```

### `predictions`
```text
PRIMARY KEY (id)
UNIQUE      (observation_id)
```

### `alerts`
```text
PRIMARY KEY (id)
UNIQUE      (prediction_id)
INDEX       (status, created_at DESC)
```

---

## 3. Breakdown of Each Index and the Query It Serves

### Index 1 — `agents(organization_id, created_at DESC)`
**Serves:**
```http
GET /api/v1/agents
```
```sql
SELECT * FROM agents
WHERE organization_id = ?
ORDER BY created_at DESC;
```
**Why composite?** We filter on `organization_id` and then sort by `created_at`. A single composite index lets PostgreSQL perform filtering and sorting in one pass instead of separate Filter-then-Sort steps.

---

### Index 2 — `observations(agent_id, received_at DESC)`
**Serves:**
```http
GET /api/v1/agents/{id}/observations
```
```sql
SELECT * FROM observations
WHERE agent_id = ?
ORDER BY received_at DESC
LIMIT 20;
```

---

### Index 3 — `observations(organization_id, received_at DESC)`
**Serves:**
```http
GET /api/v1/observations
```
```sql
SELECT * FROM observations
WHERE organization_id = ?
ORDER BY received_at DESC;
```

---

### Index 4 — `alerts(status, created_at DESC)`
**Serves:** Dashboard — latest open alerts.
```sql
SELECT * FROM alerts
WHERE status = 'OPEN'
ORDER BY created_at DESC
LIMIT 10;
```
**Why composite instead of an index on `status` alone?** Sorting is a core part of the query itself, and the composite index serves both efficiently in one operation.

---

### Index 5 — `observations(analysis_status, received_at ASC)`
**Serves:** The Worker — the single most important query in the entire backend.
```sql
SELECT * FROM observations
WHERE analysis_status = 'PENDING'
ORDER BY received_at ASC
LIMIT 1;
```

---

### Index 6 — `users(email)` (Unique)
**Serves:** Login.
```sql
SELECT * FROM users WHERE email = ?;
```

---

### Index 7 — `api_keys(key_hash, status)`
**Serves:** SDK Authentication.
```sql
SELECT * FROM api_keys
WHERE key_hash = ? AND status = 'ACTIVE';
```
**Why, given that `key_hash` is already unique?** Including `status` in the index makes query execution clearer and faster, especially when old keys (`REVOKED`) are retained in the same table, while `key_hash` itself remains globally unique.

---

## 4. Queries That Were Considered and Deliberately NOT Indexed

| Query | Decision | Reason |
|-------|----------|--------|
| `predictions ORDER BY risk_score DESC LIMIT 10` (Dashboard) | ❌ No index | The Dashboard typically surfaces this data via `alerts`, not by browsing all Predictions directly. Can be added later only if the actual need arises |
| `agents WHERE status = ?` | ❌ No index | No current endpoint filters by status |
| `agents WHERE framework = ?` | ❌ No index | No current endpoint filters by framework in V1 |
| `predictions WHERE verdict = ?` | ❌ No index | Low cardinality (only 3 values) — the benefit of an index is minimal |
| `predictions ORDER BY analyzed_at` | ❌ No index | No real query depends on this |

---

## 5. Broader Performance-Related Decisions

| Question | Decision | Reason |
|----------|----------|--------|
| Full Text Search? | ❌ No | No search endpoint exists in V1 |
| JSON Indexing (on `raw_ases_json` / `prediction_json`)? | ❌ No | There's no direct query into the content of the JSON — it's stored as a full document only |
| Partitioning? | ❌ No (V1) | Increases management and maintenance complexity, and won't provide real value before reaching tens/hundreds of millions of records |
| Read Replicas? | ❌ No | The platform is still a small-scale SaaS |
| Materialized Views? | ❌ No | The current Dashboard is simple enough for direct querying |
| Redis Cache? | ✅ Yes, but for one thing only | Dashboard statistics only — and not part of the database design itself |

---

## 6. Closing Principle

> We design the database based on access patterns (real usage), not just data shape.

This protects us from two opposing failure modes:
- **Too few indexes** → slow queries.
- **Too many indexes** → slower inserts/updates, and wasted storage without real benefit.
