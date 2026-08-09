# 08 — API Contract

> No frozen `AUDIT_API.md`, `ORGANIZATION_API.md`, or `PROFILE_API.md` exists anywhere in `docs.zip` — every endpoint below is this folder's own design, built from the domain rules already established in [`02-domain.md`](./02-domain.md) through [`07-authorization.md`](./07-authorization.md), following the exact same conventions ([`09-API_CONVENTIONS.md`](../docs/docs/09-api-reference/09-API_CONVENTIONS.md), [`07-ERROR_CODES.md`](../docs/docs/09-api-reference/07-ERROR_CODES.md), [`08-PAGINATION.md`](../docs/docs/09-api-reference/08-PAGINATION.md)) applied consistently across every prior module.

---

## 0. Conventions Recap

```text
Base path:      /api/v1
Auth:            Bearer JWT (Human) — no Agent access anywhere in this file
Error shape:     { "error": { "code": "...", "message": "...", "details": {} } }
```

---

## Organization Module

### 1. `GET /api/v1/organization`

**Role:** Owner, Admin, Member

```json
{
  "data": {
    "id": "0198a0f0-...",
    "name": "Acme Security",
    "slug": "acme-security",
    "status": "ACTIVE",
    "created_at": "2026-01-10T08:00:00Z",
    "updated_at": "2026-06-01T12:00:00Z"
  }
}
```

### 2. `PATCH /api/v1/organization`

**Role:** Owner only

**Request**
```json
{ "name": "Acme Security Inc." }
```

**Response `200 OK`** — same shape as §1, updated.

**Errors**
| HTTP | Code | Cause |
|------|------|-------|
| 422 | `VALIDATION_ERROR` | Missing/invalid `name`, or attempt to set `slug`/`status` (rejected outright, not silently ignored — same discipline as `docs/backend/agent/06-api-contract.md` §4) |
| 403 | `FORBIDDEN` | Caller's Role is `Admin` or `Member` |

---

## Authentication Module (Profile Extension)

### 3. `PATCH /api/v1/me`

**Role:** any authenticated Human (own record only)

**Request**
```json
{ "full_name": "Ahmed Updated" }
```

**Response `200 OK`**
```json
{ "data": { "id": "...", "email": "...", "full_name": "Ahmed Updated", "role": "OWNER" } }
```

**Errors**
| HTTP | Code | Cause |
|------|------|-------|
| 422 | `VALIDATION_ERROR` | Missing/invalid `full_name`, or attempt to set `email`/`role` |

### 4. `POST /api/v1/me/change-password`

**Role:** any authenticated Human (own record only)

**Request**
```json
{
  "current_password": "old-password",
  "new_password": "new-password-123",
  "new_password_confirmation": "new-password-123"
}
```

**Response `200 OK`**
```json
{ "status": "success", "message": "Password changed successfully" }
```

**Errors**
| HTTP | Code | Cause |
|------|------|-------|
| 422 | `VALIDATION_ERROR` | Weak/missing `new_password`, mismatched confirmation |
| 401 | `UNAUTHORIZED` | `current_password` doesn't match |

---

## Audit Module

### 5. `GET /api/v1/audit-logs`

**Role:** Owner, Admin

**Query Parameters**
```text
page          integer, optional, default 1
per_page      integer, optional, default 20
actor_id      string (UUID), optional — filter to one User
action        string, optional — exact match, e.g. "agent.archived"
resource_type string, optional — e.g. "Agent"
from / to     ISO 8601 date, optional — created_at range
```

**Response `200 OK`**
```json
{
  "data": [
    {
      "id": "0198c6a0-...",
      "actor_type": "USER",
      "actor_id": "0198a0e1-...",
      "action": "agent.archived",
      "resource_type": "Agent",
      "resource_id": "0198a1b2-...",
      "metadata": { "agent_name": "Support Agent" },
      "created_at": "2026-07-29T11:00:00Z"
    }
  ],
  "pagination": { "page": 1, "per_page": 20, "total_items": 143, "total_pages": 8 }
}
```

### 6. `GET /api/v1/audit-logs/{id}`

**Role:** Owner, Admin

**Response `200 OK`** — same shape as one item in §5's `data` array.

**Errors** (both §5 and §6)
| HTTP | Code | Cause |
|------|------|-------|
| 403 | `FORBIDDEN` | Caller's Role is `Member` |
| 404 | `NOT_FOUND` | (§6 only) Entry doesn't exist, or belongs to a different Organization |

### 7. `GET /api/v1/security-logs`

**Role:** Owner, Admin

Same query parameters and response shape as §5, with `action` implicitly restricted to the fixed list in [`06-security-logs.md`](./06-security-logs.md) §2 — the `action` query parameter, if also supplied, further narrows within that fixed set rather than expanding beyond it.

**Errors** — same as §5/§6.

---

## Summary — Endpoint Ownership

```text
Organization Module          Authentication Module (extended)     Audit Module (new)
────────────────────         ──────────────────────────────       ────────────────────
GET   /organization           PATCH /me                             GET /audit-logs
PATCH /organization             POST /me/change-password               GET /audit-logs/{id}
                                                                          GET /security-logs
```
