# 05 — API Keys

> Source: Session 5 — API Key Design
> This is arguably the most consequential session after Session 2, because the API Key is the main door through which every AI Agent enters the platform. Get this right, and the SDK, Backend, and Security all stay simple. Get it wrong, and the project circles the same problems forever.

---

## 1. Is the API Key the Agent's Identity?

**No** — the first point that must be nailed down. We already agreed in Session 2:

```text
Identity ≠ Credential
```

So: the **Agent is the Identity**. The **API Key is merely a Credential**, exactly parallel to:

```text
Human → Password
Agent → API Key
```

---

## 2. What Is an API Key, Exactly?

> **The API Key is a long-lived credential that allows the Agent to prove its identity on every request.**

Focus on:

> **Long-lived Credential**

Not a Session. Not a Token.

---

## 3. Why an API Key at All? Why Not JWT for Agents?

The answer is simple: an Agent's nature is fundamentally different from a human's.

```text
Human:  Opens Dashboard → Login → Works for an hour → Closes
Agent:  Runs 24/7 → Sends thousands of requests → No human interaction
```

This means the Agent needs a **stable Credential** — and that's the real reason we chose API Key.

---

## 4. Does the API Key Change on Every Request?

**No.** Does it change daily? **No.**

It stays fixed until the organization decides to:

```text
Rotate
or
Revoke
```

This means its lifecycle is fundamentally different from a JWT's.

---

## 5. Does the API Key Represent the Organization?

**Indirectly.** We deliberately keep the SDK from sending `Organization ID` and `Agent ID` on every request — the SDK needs to stay simple.

So: the **API Key** is what tells us:

```text
Organization
    ↓
Agent
```

This was one of the earliest decisions made in the entire project.

---

## 6. What Happens When a Request Arrives?

```text
Receive Request
    ↓
Extract API Key
    ↓
Verify API Key
    ↓
Resolve Agent Identity
    ↓
Resolve Organization
    ↓
Build Authenticated Identity
    ↓
Authorization
    ↓
Business Logic
```

The Controller **never** touches the raw API Key — it only ever sees the **Authenticated Identity**. This is a direct extension of everything built in previous sessions.

---

## 7. Do We Store the Raw API Key in the Database?

**No** — one of the most important decisions in the whole project.

We store:

```text
Hash(API Key)
```

not the raw key — exactly like a Password.

**Why?** If the database were ever leaked, an attacker would still not be able to use the keys.

> **API Keys are treated exactly like Passwords.**

This is a production-grade rule. See the exact format in [`contracts/api-key-format.md`](./contracts/api-key-format.md).

---

## 8. How Many Times Does the User See the Key After Creating It?

**Exactly once.** This mirrors exactly what GitHub, Stripe, and similar platforms do.

```text
Generate
    ↓
Display Once
    ↓
Hash & Store
    ↓
Never Display Again
```

If it's lost, generate a new one.

---

## 9. Can an Agent Have More Than One API Key?

Considered carefully. Two schools of thought:

- **One key per Agent.**
- **Multiple Keys.**

For the MVP: **one key per Agent** — simpler, clearer, and doesn't complicate the SDK.

If we later need:
```text
Blue/Green Rotation
Multiple Environments
Zero Downtime Rotation
```
we can then add Multiple Keys — but not right now.

---

## 10. Does the API Key Expire Automatically?

**No.** It stays Active until one of:

```text
Rotate
Revoke
Agent Archived
```

This fits the nature of Agents well.

---

## 10a. Every Status Writer, in One Place (STATE-005)

`ApiKeyStatus` has three legitimate writers, spread across two modules — the integration audit (Session 03, STATE-005) found no functional problem with any of them, only that a future reader would have to already know to look in a model hook and a cross-module listener, in addition to the obvious explicit rotation Action. Documented here, in one place, so that's no longer necessary:

```text
1. GenerateApiKeyAction        — creates a new key as ACTIVE (the initial write)
2. ApiKey model's saved() hook — enforces "at most one ACTIVE key per Agent"
                                    by revoking any prior ACTIVE key when a new
                                    one is saved as ACTIVE (belongs to the
                                    Authentication\ApiKey module itself)
3. RevokeKeysOnAgentArchived    — a listener, owned by the Agent module's
                                    boundary, that revokes every ACTIVE key
                                    for an Agent when that Agent is archived
                                    (cross-module: Agent → Authentication\ApiKey)
```

No change was made to any of these three — each is legitimately scoped to the concern that owns it. This section exists purely so all three are discoverable from the API Key's own documentation, not just from reading the code.

## 10b. `expires_at` Is a Second, Currently Dormant Status Dimension (STATE-006)

The `api_keys` table has an `expires_at` column, and `ValidateApiKeyAction` already checks it (`$apiKey->expires_at && $apiKey->expires_at->isPast()` rejects the key). **Nothing in this codebase currently writes to `expires_at`** — every API Key created today has `expires_at = null` and is therefore never subject to this check in practice. This is dormant, not broken: `status: ACTIVE` alone is **not**, by itself, sufficient to conclude a key is currently usable — `expires_at` is a second, independent dimension that must also be checked, and already is. If real expiry ever becomes a planned feature (e.g. a scheduled job that flips `status` to `REVOKED` once `expires_at` passes, or a "create with expiry" option on `GenerateApiKeyAction`), that's new, deliberate feature work — not a bug fix to this dormant column, since nothing yet populates the field such a job would react to.

---

## 11. Is the API Key Part of the Domain?

**Yes** — and this differs from the JWT's answer.

**Why?** Because the API Key has a full lifecycle in our system:

```text
Create
Rotate
Revoke
Last Used
Status
```

This means it is a **real Entity** in the system, which is exactly why we gave it its own dedicated database table from the start.

---

## 12. Does the SDK Know Anything Other Than the API Key?

**No** — one of the most elegant decisions made in the project. The SDK stays extremely simple. It knows:

```text
API Key
+
API URL
```

That's it. No Organization ID. No Agent ID. No identity-related metadata whatsoever. The Backend derives all of that on its own.

---

## 13. The Complete Flow

```text
Agent
    ↓
API Key
    ↓
HTTP Request
    ↓
Extract API Key
    ↓
Hash Comparison
    ↓
Resolve Agent
    ↓
Resolve Organization
    ↓
Authenticated Identity
    ↓
Authorization
    ↓
Business Logic
```

---

## 14. Important Architectural Comparison

```text
JWT
✔ Short-lived
✔ Human
✔ Stateless
✔ Issued after Login

────────────────────

API Key
✔ Long-lived
✔ Agent
✔ Stable Credential
✔ Created once
✔ Displayed once
✔ Stored as Hash
```

Both solve the exact same problem (proving identity), but each fits a different actor's nature — which is exactly why we refused to unify them into a single mechanism.

---

## 15. Session 5 Summary

```text
API Key Design

Purpose
Authenticate Agent Identities.

────────────────────────

Represents
Credential
NOT Identity

────────────────────────

Lifecycle
Create
    ↓
Display Once
    ↓
Hash & Store
    ↓
Use
    ↓
Rotate / Revoke

────────────────────────

Rules
✔ One API Key per Agent (MVP)
✔ Long-lived
✔ Stored as Hash
✔ Never shown again
✔ Resolves Agent & Organization
✔ Produces Authenticated Identity
✔ No Organization ID or Agent ID required from SDK
```

---

## 16. The Most Important Decision in This Session

> **The SDK carries no responsibility for answering "which organization do I belong to?" or "which Agent am I?" — its only responsibility is to supply the API Key.**

Everything else — verifying the key, identifying the Agent, identifying the Organization, building the Authenticated Identity — is done by the platform. This is a smart decision because it achieves two things at once:

1. It simplifies the SDK developer's experience — nothing to configure beyond a single value.
2. It preserves the source of truth inside the platform — Organization and Agent are always derived from the database, never from client-supplied data that could be forged or wrong.
