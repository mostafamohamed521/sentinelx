# Endpoint Inspection Report

---

# Endpoint

- Method: GET
- Route: `/v1/security-logs`
- Endpoint Name: List Security Logs
- Purpose: Returns a paginated, filterable list of Audit Log entries restricted to a fixed set of security-relevant actions, for the caller's own Organization. Owner and Admin only.

---

# Routing

- API Version: v1
- Controller: `App\Modules\Audit\API\Controllers\SecurityLogController`
- Controller Method: `index`
- Middleware: `auth:api`, `throttle:api` (enclosing route group), `App\Modules\Audit\API\Middleware\EnsureOwnerOrAdminRole` (route-group specific)
- Throttle: `api` rate limiter — 120 requests per minute, keyed by the authenticated user's ID (falls back to IP) (`AppServiceProvider::registerRateLimiters`)
- Authentication Guard: `api` (JWT — `config/auth.php` `guards.api.driver = 'jwt'`)
- Required Role: `OWNER` or `ADMIN` — enforced by `Audit\API\Middleware\EnsureOwnerOrAdminRole`, which checks `$user->role?->value` is one of `['OWNER', 'ADMIN']`; Member is explicitly excluded.

---

# Request

## Headers

- `Authorization: Bearer {token}` — required by the `auth:api` middleware.

## Path Parameters

None.

## Query Parameters

- `actor_id` — optional; filters to a single actor's ID.
- `action` — optional; per `GetSecurityLogsAction`'s own comment, "narrows WITHIN the fixed security set — it never expands beyond it. An `action` outside the set correctly yields zero rows ... rather than silently falling back to the full set."
- `resource_type` — optional; filters to an exact `resource_type` string.
- `from` — optional; parsed via `Carbon::parse()`, filters `created_at >= from`.
- `to` — optional; parsed via `Carbon::parse()`, filters `created_at <= to`.
- `page` — optional; defaults to `1` (`$request->integer('page', 1)`).
- `per_page` — optional; defaults to `20`, clamped to a maximum of `100` (`Controller::perPage()`, `MAX_PER_PAGE = 100`).

## Request Body

None.

## Validation Rules

Not found in the implementation — no Form Request is used for this endpoint; query parameters are read and coerced directly in the controller (via the shared `ResolvesAuditListQuery` trait, also used by `AuditController`).

---

# Processing

- Service: Not found in the implementation.
- DTO: Not found in the implementation.
- Action: `GetSecurityLogsAction::handle(organizationId, actorId, action, resourceType, from, to, perPage, page)` — per its own doc-block, "a thin wrapper — calls the SAME `AuditRepository::listForOrganization()` used by the general Audit Log endpoint, with a fixed action-list filter applied. No second table, no second write path."
- Business Rules:
  - `organization_id` is always server-derived from the authenticated Human's JWT (`ResolvesAuditListQuery::organizationId()`), never accepted from the request.
  - `SECURITY_ACTIONS` — a fixed list of 7 actions: `user.registered`, `user.logged_in`, `user.logged_out`, `user.password_changed`, `api_key.generated`, `api_key.rotated`, `api_key.revoked`. Per the Action's own doc-block, this is "an engineering default, explicitly flagged as easily revised, same discipline as Alert's severity thresholds."
  - If no `action` query parameter is supplied, `actionIn = SECURITY_ACTIONS` (the full fixed set).
  - If `action` is supplied and is one of `SECURITY_ACTIONS`, `actionIn = [$action]` (narrows within the set).
  - If `action` is supplied but is NOT one of `SECURITY_ACTIONS`, `actionIn = []` — an explicit empty-array `whereIn`, matching nothing, rather than falling back to the unfiltered set.
  - The underlying repository call always passes `action: null` (the plain `action` exact-match filter is never used here — only `actionIn`).
  - Delegates to the same `AuditRepository::listForOrganization()` used by `GET /v1/audit-logs`, with `actionIn` populated as above.
- Database Operations: `AuditLog::query()->where('organization_id', ...)->when($actorId, ...)->when($resourceType, ...)->when($from, ...)->when($to, ...)->whereIn('action', $actionIn)->orderByDesc('created_at')->paginate(...)` (`AuditRepository::listForOrganization()`).
- Events: Not found in the implementation.
- Jobs: Not found in the implementation.
- Notifications: Not found in the implementation.

---

# Response

## Success Status Code

200

## Response Resource

`App\Modules\Audit\Presentation\AuditLogCollection` (wraps `AuditLogResource`) — the same Resource/Collection pair used by `GET /v1/audit-logs`.

## Response Structure

```
{
  "data": [
    {
      "id": ...,
      "actor_type": ...,
      "actor_id": ...,
      "action": ...,
      "resource_type": ...,
      "resource_id": ...,
      "metadata": ...,
      "created_at": ...
    }
  ],
  "pagination": {
    "page": ...,
    "per_page": ...,
    "total_items": ...,
    "total_pages": ...
  }
}
```

---

# Error Responses

| Status | Condition |
|---------|-----------|
|401|No token provided, an invalid/expired token, or an Agent (API Key) with no JWT — rendered via the global `AuthenticationException` render callback (`bootstrap/app.php`), which reuses `AuthenticationFailedException::render()`: `error.code = AUTHENTICATION_FAILED`.|
|403|Caller's role is `MEMBER` (neither `OWNER` nor `ADMIN`) — rendered by `AuditForbiddenException::render()`: `error.code = FORBIDDEN`.|
|429|Rate limit exceeded — `throttle:api` (120 requests/minute per authenticated user). Rendered by Laravel's default throttle response (not customized for this route).|

Only include implemented responses.

---

# Side Effects

- Database Changes: Not found in the implementation.
- Audit Logs: Not applicable — this endpoint reads `audit_logs` (filtered), it does not write to it.
- Security Logs: Not applicable — there is no separate `security_logs` table; per `GetSecurityLogsAction`'s own doc-block, "No second table, no second write path."
- Events: Not found in the implementation.
- Cache: Not found in the implementation.
- Notifications: Not found in the implementation.

---

# Dependencies

- Models:
  - `App\Modules\Audit\Infrastructure\Persistence\AuditLog`
- Resources:
  - `App\Modules\Audit\Presentation\AuditLogCollection`
  - `App\Modules\Audit\Presentation\AuditLogResource`
- Services: Not found in the implementation. (Uses Action class: `GetSecurityLogsAction`, and `AuditRepository` for persistence access.)
- Policies: Not found in the implementation.
- Enums:
  - `App\Modules\Audit\Domain\ActorType` (`USER`, `SYSTEM`)
- Traits:
  - `App\Modules\Audit\API\Controllers\Concerns\ResolvesAuditListQuery` (shared with `AuditController`; provides `organizationId()` and `dateQuery()`)

---

# Postman Collection Notes

## Authorization

Bearer token (`Authorization: Bearer {access_token}`) for a User whose `role` is `OWNER` or `ADMIN`.

## Variables To Save

Not found in the implementation.

## Pre-request Requirements

A valid `access_token` for an authenticated Owner- or Admin-role User must be available.

## Post-request Automation

Not found in the implementation.

## Suggested Test Cases

Existing coverage found in `backend/tests/Feature/Audit/SecurityLogsTest.php`:

- Owner can list security logs, containing only security-relevant actions.
- Security logs never return an `organization.updated` or `agent.archived` entry, even though both exist in the general Audit Log.
- An `action` filter outside the security set narrows to zero results, never expanding beyond the fixed set.
- An `action` filter inside the security set narrows correctly.
- A member cannot view security logs (403).
- An admin can view security logs.
- Security logs are scoped to the caller's own organization (data isolation).

## Collection Folder

Not found in the implementation.

## Execution Order

Not found in the implementation.

---

# Related Endpoints

- `GET /v1/audit-logs`
- `GET /v1/audit-logs/{auditLogId}`
