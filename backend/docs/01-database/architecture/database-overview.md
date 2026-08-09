# Database Overview

> Answers one question: **"Why was the database designed this way?"**

---

## 1. The Business Story

The SentinelX database isn't the result of "let's build some tables" — it's the result of a full story that was defined first:

```text
A Organization registers (Register Organization)
        ↓
It adds Agents
        ↓
An Agent sends an Observation
        ↓
ML analyzes the Observation
        ↓
Prediction
        ↓
If risky → Alert
```

Every table in the database is a direct translation of a step in this story. No table was added "because it might be useful later" — every table serves a real step in the business flow.

---

## 2. Who Uses the Database?

The SentinelX database isn't owned by a single layer — it's the **Shared Contract** between every part of the system:

| Party | How it interacts with the database |
|-------|--------------------------------------|
| **SDK** | Sends a new Observation via `POST /observations`, stored as `raw_ases_json` in the `observations` table |
| **Backend (Laravel/API)** | Reads and writes all structured tables (organizations, users, agents, api_keys, alerts), manages the full lifecycle |
| **ML Service** | Consumes `observation.raw_ases_json`, analyzes it, and returns a result stored as `prediction_json` plus extracted columns (`verdict`, `risk_score`, `confidence`) |
| **Dashboard** | Read-heavy — relies primarily on structured columns (not JSON) for display, filtering, and sorting |
| **Worker / Queue** | Queries `observations WHERE analysis_status = 'PENDING'` to hand off work to ML |

This philosophy is what drove the decision of **"what becomes a column vs. what stays JSON"** — any data multiple parties will repeatedly query becomes a column; everything else stays inside the JSON document.

---

## 3. Top-Level Philosophy: Business Data Model Before Database

From the very first session, it was established that we don't design "tables" — we first design a **Business Data Model**, and only then translate it into a database.

The question we ask is never:

> "Create a table called Observations"

But rather:

> "What data needs to live for the lifetime of the system?"

The answer turned out to be **exactly 7 entities** worth persisting:

```text
Organization → Users → Agents → Observations → Predictions → Alerts → API Keys
```

There is no eighth entity, and no table was added "just to exist."

---

## 4. Hybrid Data Model

The SentinelX database is built on a hybrid model between two storage types:

### Type One — Structured (regular columns)
```text
Organization · User · Agent · API Key · Alert
```
Bounded-shape data, queried frequently, requiring clear constraints.

### Type Two — Document (JSONB)
```text
Observation → raw_ases_json
Prediction  → prediction_json
```
Data coming from external sources with a variable shape (SDK / ML), where the real value lies in **preserving the full original shape**.

The governing rule:

> **Store for Query, Keep the Rest as Document.**
> We only store as a column what we'll actually query, filter, or sort on. Everything else stays JSON.

Full reasoning is in [`decisions/adr-003-json-storage.md`](../decisions/adr-003-json-storage.md).

---

## 5. Multi-Tenancy From Day One

The real client of the SentinelX platform is **Organization**, not **User**. A user registers a organization (`Register Organization`), not a personal account.

```text
SentinelX
   │
   ├── Microsoft
   │      ├── Ahmed, Omar (Users)
   │      └── 12 Agents
   │
   ├── Google
   │      ├── John (User)
   │      └── 30 Agents
   │
   └── Amazon
          └── ...
```

This decision shaped everything that came after: nearly every business table carries `organization_id`, and nearly every query begins by filtering on the organization. Full details in [`decisions/adr-005-multi-tenancy.md`](../decisions/adr-005-multi-tenancy.md).

---

## 6. Query-Driven Design

The database was designed **after** the REST API, not before. This was deliberate:

> We waited to know exactly what the endpoints would actually return and filter on, before deciding on columns and indexes.

The result: every column extracted from JSON (like `risk_score`, `verdict`, `analysis_status`) and every index that exists serves a **real query** — no "just in case" columns or indexes.

---

## 7. Security & Audit as a Baseline

SentinelX is a security platform, and this shaped the database design itself:

- **No physical deletes** in the business flow — Archive instead of Delete.
- **API Keys and Passwords are hashed only**, never stored as plain text.
- **Every Observation and Prediction persists forever** — so that at any point we can go back and answer "how did the old model see this event?"

This database is not just a storage location — it's a **System of Record** that can be trusted in any future audit or investigation.
