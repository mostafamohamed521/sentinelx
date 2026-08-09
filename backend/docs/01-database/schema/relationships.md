# Relationships & Foreign Keys

> Every relationship here is a **frozen** decision. Any change to its cardinality or delete rule counts as an architectural change and must be documented as a new ADR.

---

## 1. Full Relationship Map

```text
Organization
│
├──── Users              (1 → ∞)
│
├──── Agents             (1 → ∞)
│      │
│      └──── API Keys    (1 → ∞)
│
└──── Observations       (1 → ∞)   ← Denormalized FK
        │
        └──── Predictions (1 → 0..1)
                │
                └──── Alerts (1 → 0..1)
```

---

## 2. Detailed Relationship Table

| # | Relationship | Cardinality | Foreign Key | Delete Rule |
|---|--------------|-------------|--------------|-------------|
| 1 | Organization → Users | 1 → ∞ | `users.organization_id → organizations.id` | `RESTRICT` |
| 2 | Organization → Agents | 1 → ∞ | `agents.organization_id → organizations.id` | `RESTRICT` |
| 3 | Agent → API Keys | 1 → ∞ | `api_keys.agent_id → agents.id` | `RESTRICT` |
| 4 | Agent → Observations | 1 → ∞ | `observations.agent_id → agents.id` | `RESTRICT` |
| 5 | Organization → Observations | 1 → ∞ | `observations.organization_id → organizations.id` | `RESTRICT` |
| 6 | Observation → Prediction | 1 → 0..1 | `predictions.observation_id → observations.id` (UNIQUE) | `RESTRICT` |
| 7 | Prediction → Alert | 1 → 0..1 | `alerts.prediction_id → predictions.id` (UNIQUE) | `RESTRICT` |

---

## 3. Explanation of Each Relationship

### Organization → Users
```text
Organization (1) ──── Users (∞)
```
One organization has many users, and a user belongs to exactly one organization. Deleting a organization that still has users is considered a **business error**, hence `RESTRICT`.

### Organization → Agents
```text
Organization (1) ──── Agents (∞)
```
Same logic exactly — a organization cannot be deleted while it still has agents attached.

### Agent → API Keys
```text
Agent (1) ──── API Keys (∞)
```
The relationship is **One-to-Many, not One-to-One**, on purpose, to support key rotation and preserve old keys as audit records (`REVOKED`).

**Business Rule (application layer, not the database):** the count of `ACTIVE` keys per Agent must be ≤ 1 at any time.

### Agent → Observations
```text
Agent (1) ──── Observations (∞)
```
Every Observation must have an Agent (no "orphan" Observation is allowed). Agents are never physically deleted (Archive only), so `RESTRICT` makes complete logical sense.

### Organization → Observations (Denormalized)
```text
Organization (1) ──── Observations (∞)
```
Even though the organization can be derived via `agent_id → agents.organization_id`, `organization_id` is deliberately duplicated inside `observations` **to improve performance** — see the full explanation in [`entities.md`](./entities.md#5-observations) and the denormalization section in [`design-principles.md`](../architecture/design-principles.md#9-normalize-by-default-denormalize-with-purpose).

### Observation → Prediction
```text
Observation (1) ──── Prediction (0..1)
```
**Not 1..1** — when an Observation first arrives, its state is `PENDING` with no Prediction attached yet. Only after ML analysis completes does the linked Prediction appear. The `UNIQUE(observation_id)` constraint ensures at most one Prediction per Observation in V1.

### Prediction → Alert
```text
Prediction (1) ──── Alert (0..1)
```
**Optional**, because a `SAFE` verdict will never produce an Alert. The `UNIQUE(prediction_id)` constraint ensures at most one Alert per Prediction.

---

## 4. Delete Strategy

### The Decision: `RESTRICT` Everywhere — No Exceptions

```text
✔ ON DELETE RESTRICT  (on every foreign key)
✘ No CASCADE
✘ No SET NULL
```

### Why not CASCADE?

If we used `CASCADE`, any accidental deletion of a Organization record would trigger a cascading deletion of everything beneath it:

```text
Organization → Users → Agents → API Keys → Observations → Predictions → Alerts
```

That means an entire organization's history could disappear because of a single mistake (a bug or a human deletion). On a security platform, that's catastrophic. The platform already relies on **Archive**, not **Delete**, so cascading deletes serve no real purpose whatsoever.

### Why not SET NULL?

`SET NULL` would allow "orphan" records to exist — such as an Observation with no Agent, or an Alert with no Prediction. This is entirely rejected because it violates the [Parent Must Always Exist](../architecture/design-principles.md#10-parent-must-always-exist-referential-integrity) principle.

### Why no Soft Delete (`deleted_at`)?

The platform already has a clear lifecycle that accurately expresses the actual state of the data:

```text
Organization → SUSPENDED
Agent   → ARCHIVED
Alert   → RESOLVED
```

Adding a `deleted_at` column on top of this would introduce extra complexity without real value. See [`decisions/adr-002-soft-delete-strategy.md`](../decisions/adr-002-soft-delete-strategy.md) for full details.

---

## 5. Referential Integrity — The Golden Rule

> **Every record in the system must have a valid parent.**

```text
✘ Observation.agent_id       = NULL   → not allowed
✘ Prediction.observation_id  = NULL   → not allowed
✘ Alert.prediction_id        = NULL   → not allowed
```

All relationships are mandatory except what was logically agreed to be optional (whether a Prediction or Alert exists at all) — but **if the record exists, its parent must always exist**.

---

## 6. Why Calculated Denormalization Rather Than Pure Academic Normalization?

The recurring decision across the entire database:

> Normalize by default... Denormalize only when it clearly improves real business queries.

The single actual instance of this in V1 is `observations.organization_id`. There is no other duplication anywhere else — every other case is fully normalized.
