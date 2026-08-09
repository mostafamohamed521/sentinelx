---
title: Authentication API
category: API Reference
status: Approved
version: v1
---

# Authentication API

## POST /api/v1/auth/login

Authenticates a dashboard user.

### Authentication

None

### Request Body

```json
{
  "email": "user@example.com",
  "password": "********"
}
```

### Success

```
200 OK
```

Returns:

- JWT Access Token
- User information

---

## POST /api/v1/auth/logout

Removes the access token client-side. No server-side token invalidation occurs in V1 — JWTs are stateless and short-lived by design. Server-side Token Revocation is deferred to a future version. See `backend/docs/00-backend_architecture/adr/ADR-005-stateless-jwt-logout.md`.

### Authentication

JWT

### Success

```
204 No Content
```

---

## GET /api/v1/auth/me

Returns the currently authenticated user.

### Authentication

JWT

### Success

```
200 OK
```

Returns the authenticated user's profile.