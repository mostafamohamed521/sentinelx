# 06 — Agent API Contract

> Expands [`docs.zip/09-api-reference/03-AGENTS_API.md`](../docs/docs/09-api-reference/03-AGENTS_API.md) into an implementation-ready contract. Every endpoint listed there is covered here in full — no endpoint is added or removed. Response envelope, error shape, and pagination shape follow [`09-API_CONVENTIONS.md`](../docs/docs/09-api-reference/09-API_CONVENTIONS.md), [`07-ERROR_CODES.md`](../docs/docs/09-api-reference/07-ERROR_CODES.md), and [`08-PAGINATION.md`](../docs/docs/09-api-reference/08-PAGINATION.md) exactly.

---

## 0. Conventions Recap (Do Not Redefine, Only Apply)

```text
Base path:      /api/v1
Auth:            Bearer JWT (Human) — required on every endpoint in this file
Content-Type:    application/json
Timestamps:      ISO 8601, UTC  (e.g. 2026-07-29T10:15:00Z)
Error shape:     { "error": { "code": "...", "message": "...", "details": {} } }
```

---

## 1. `GET /api/v1/agents`

**Owner:** Agent module · **Role:** Owner, Member

Returns all Agents belonging to the authenticated Organization, paginated.

**Query Parameters**
```text
page       integer, optional, default 1
per_page   integer, optional, default 20
status     string, optional — ACTIVE | ARCHIVED (filter)
```

**Response `200 OK`**
```json
{
  "data": [
    {
      "id": "0198a1b2-...",
      "name": "Support Agent",
      "framework": "CrewAI",
      "framework_version": "1.2.0",
      "description": "Handles tier-1 customer support tickets",
      "status": "ACTIVE",
      "last_seen_at": "2026-07-29T09:58:11Z",
      "created_at": "2026-07-01T12:00:00Z",
      "updated_at": "2026-07-01T12:00:00Z"
    }
  ],
  "pagination": {
    "page": 1,
    "per_page": 20,
    "total_items": 4,
    "total_pages": 1
  }
}
```

**Note:** never includes any API Key field — this response comes entirely from the `agents` table.

---

## 2. `POST /api/v1/agents`

**Owner:** Agent module · **Role:** Owner only

Creates a new Agent. Never generates a credential (see [`03-lifecycle.md`](./03-lifecycle.md) §5).

**Request**
```json
{
  "name": "Support Agent",
  "framework": "CrewAI",
  "framework_version": "1.2.0",
  "description": "Handles tier-1 customer support tickets"
}
```

**Validation**
```text
name                required, string, 1-255
framework           required, string, 1-100
framework_version   optional, string, max 50
description         optional, string, max 2000
```

**Response `201 Created`**
```json
{
  "data": {
    "id": "0198a1b2-...",
    "name": "Support Agent",
    "framework": "CrewAI",
    "framework_version": "1.2.0",
    "description": "Handles tier-1 customer support tickets",
    "status": "ACTIVE",
    "last_seen_at": null,
    "created_at": "2026-07-29T10:00:00Z",
    "updated_at": "2026-07-29T10:00:00Z"
  }
}
```

**Errors**
| HTTP | Code | Cause |
|------|------|-------|
| 422 | `VALIDATION_ERROR` | Missing/invalid `name` or `framework` |
| 409 | `CONFLICT` | `name` already exists within this Organization |
| 403 | `FORBIDDEN` | Caller's Role is `Member` |

---

## 3. `GET /api/v1/agents/{agentId}`

**Owner:** Agent module · **Role:** Owner, Member

**Response `200 OK`** — same shape as a single item in §1's `data` array.

**Errors**
| HTTP | Code | Cause |
|------|------|-------|
| 404 | `NOT_FOUND` | Agent doesn't exist, **or** belongs to a different Organization (indistinguishable by design — see [`05-authorization.md`](./05-authorization.md) §4) |

---

## 4. `PATCH /api/v1/agents/{agentId}`

**Owner:** Agent module · **Role:** Owner only

Updates mutable metadata only.

**Request** (all fields optional; at least one required)
```json
{
  "name": "Support Agent v2",
  "description": "Now also handles tier-2 escalations"
}
```

**Response `200 OK`** — full updated Agent object, same shape as §3.

**Errors**
| HTTP | Code | Cause |
|------|------|-------|
| 422 | `VALIDATION_ERROR` | Invalid field value, or empty body |
| 409 | `CONFLICT` | New `name` collides with another Agent in the same Organization |
| 404 | `NOT_FOUND` | Cross-tenant or non-existent Agent |
| 403 | `FORBIDDEN` | Caller's Role is `Member` |
| 422 | `VALIDATION_ERROR` | Attempt to set `status` or `organization_id` directly (these fields are not accepted in this request at all — silently ignored is **not** acceptable; reject the request instead, see note below) |

> **Note on rejecting unknown/forbidden fields:** if the request body contains `status` or `organization_id`, the FormRequest/validator must reject the request with `422`, not silently strip the field. Silent stripping hides a client-side bug from the caller.

---

## 5. `PATCH /api/v1/agents/{agentId}/archive`

**Owner:** Agent module · **Role:** Owner only

No request body.

**Response `200 OK`**
```json
{
  "data": {
    "id": "0198a1b2-...",
    "status": "ARCHIVED",
    "updated_at": "2026-07-29T10:20:00Z"
  }
}
```

**Side effect (not part of the HTTP response, documented for completeness):** dispatches `AgentArchived`, which the API Key submodule reacts to by revoking the Agent's active key. See [`04-api-key-coordination.md`](./04-api-key-coordination.md) §4.

**Errors**
| HTTP | Code | Cause |
|------|------|-------|
| 404 | `NOT_FOUND` | Cross-tenant or non-existent Agent |
| 409 | `CONFLICT` | Agent is already `ARCHIVED` (idempotency — see [`02-domain.md`](./02-domain.md) §4) |
| 403 | `FORBIDDEN` | Caller's Role is `Member` |

---

## 6. `POST /api/v1/agents/{agentId}/rotate-api-key`

**Owner:** ⚠️ **Authentication module (API Key submodule)** — documented here only because it shares the URL prefix. Full contract lives in [`02-auth/contracts/api-key-format.md`](../02-auth/02-auth/contracts/api-key-format.md). Reproduced at summary level for discoverability:

**Response `201 Created`**
```json
{
  "data": {
    "key_prefix": "sk_live_ab12",
    "raw_key": "sk_live_ab12_9f8e7d6c5b4a3928...",
    "status": "ACTIVE",
    "created_at": "2026-07-29T10:01:00Z"
  }
}
```

`raw_key` is present in this response **only**, exactly once, ever. Any subsequent `GET` on API Key data (should such an endpoint exist under Authentication) never re-exposes it.

**Errors**
| HTTP | Code | Cause |
|------|------|-------|
| 404 | `NOT_FOUND` | Cross-tenant or non-existent Agent |
| 409 | `CONFLICT` | Agent is `ARCHIVED` — cannot issue a key for an inactive Agent |
| 403 | `FORBIDDEN` | Caller's Role is `Member` |

---

## 7. `GET /api/v1/agents/{agentId}/observations`

**Owner:** ⚠️ **Observation module** — documented here only for discoverability, same reasoning as §6. Full contract belongs in the (future) Observation module documentation folder (Stage 3). Not implemented as part of Stage 2 — this row exists in the contract now purely so the route table is complete and no engineer accidentally builds it inside the Agent module.

**Response `200 OK`** (shape defined by Observation module; paginated per [`08-PAGINATION.md`](../docs/docs/09-api-reference/08-PAGINATION.md))

---

## 8. Endpoint Ownership Summary

```text
Agent Module                        Authentication (API Key)      Observation Module
──────────────────                  ──────────────────────        ────────────────────
GET    /agents                                                    
POST   /agents                                                    
GET    /agents/{id}                                                
PATCH  /agents/{id}                                                
PATCH  /agents/{id}/archive                                        
                                     POST /agents/{id}/rotate-api-key
                                                                    GET /agents/{id}/observations
```

**Stage 2 implements every row in the left two columns. The right column is out of scope until Stage 3.**
