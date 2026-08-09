# ADR-004: API Key as an Independent Entity Instead of a Column Inside Agent

| | |
|---|---|
| **Status** | ✅ Accepted (Frozen) |
| **Session** | Session 5 |
| **Affects** | The independent `api_keys` table + its relationship with `agents` |

---

## Context

An Agent needs a way to prove its identity when communicating with the platform (authentication). A common pattern in many projects is adding an `api_key` column directly inside the entity's own table (`agents.api_key`). This pattern was evaluated against creating an independent table.

---

## Decision

**`api_keys` is a completely independent table from `agents`**, with a `Agent (1) → API Keys (∞)` relationship — not One-to-One, and not a column inside the Agent table.

```text
agents
    │
    └── api_keys (∞)
```

The real key is **never** stored in any form — only:
```text
key_prefix   → the visible portion for display only (e.g., sk_live_ab12)
key_hash     → a hash of the full key (Unique)
```

---

## Rationale

### 1. A Credential Has a Completely Independent Lifecycle From the Entity
An Agent is an entity (Identity), but an API Key is a **Credential** with a fully distinct lifecycle:
- Creation
- Rotation
- Revocation
- Disabling
- Tracking last use (`last_used_at`)
- Recording creation history (Audit Trail)

Merging this into a single column in `agents` makes it impossible to properly track this lifecycle.

### 2. Why One-to-Many Instead of One-to-One?
The platform needs an endpoint to rotate the key:
```http
POST /agents/{id}/rotate-api-key
```

If the relationship were One-to-One, rotation would be difficult (the old record would need to be overwritten directly, losing any trace of the previous key). With One-to-Many:
- The old key can be retained as a record with `REVOKED` status.
- This provides an excellent audit trail for anyone investigating a security incident later.
- Rotation happens with zero downtime (no interruption).

### 3. Hash Only — Never Plain Text
Just like passwords, storing the real key in any form represents a serious security risk on a platform that is fundamentally about security. `key_prefix` is only used to show the user a visual hint (like the last 4 digits of a credit card), while `key_hash` is what's actually used for authentication via comparison.

---

## Business Rule (Application Layer, Not a Database Constraint)

> The count of `ACTIVE` keys per Agent must always be ≤ 1.

The database itself technically allows more than one record for the same Agent (this is intentional, to support rotation), but the application layer is responsible for ensuring no more than one `ACTIVE` key exists at any given moment.

---

## Rejected Alternatives

| Alternative | Reason for Rejection |
|-------------|------------------------|
| An `api_key` column inside `agents` | Loses the ability to track the key's lifecycle (rotation, revocation, audit) separately from the Agent itself |
| A One-to-One relationship | Makes rotation a complex operation and loses the history of old keys |
| Storing the key as plain text (even if encrypted, not hashed) | An unacceptable security risk on a security platform |
| Adding Permissions/Scopes at the API Key level | Unnecessary over-engineering for V1 — deliberately rejected |

---

## What Was Deliberately Rejected in This Scope (V1 Scope)

- ❌ Permissions at the API Key level.
- ❌ Scopes (fine-grained permissions per key).
- ❌ Supporting more than one `ACTIVE` key at the same time.

All of these capabilities can be added later in V2 if the real need arises, without requiring a redesign of the table from scratch — because the core structure (independent table, Hash Only, One-to-Many) naturally supports this kind of expansion.

---

## Consequences

- ✅ API Key rotation with zero downtime.
- ✅ Retention of old key history for audit purposes.
- ✅ Independent tracking of last use for each key.
- ✅ Revoking a specific key without affecting the historical usage record.
- ⚠️ Requires the application layer to implement the "only one Active key" validation logic — this is not automatically guaranteed at the database level.
