# Endpoint Inspection Report

---

# Endpoint

- Method: GET
- Route: `/v1/audit-logs/{auditLogId}`
- Endpoint Name: Show Audit Log
- Purpose: Returns a single Audit Log entry belonging to the caller's own Organization. Owner and Admin only.

---

# Routing

- API Version: v1
- Controller: `App\Modules\Audit\API\Controllers\AuditController`
- Controller Method: `show`
- Middleware: `auth:api`, `throttle:api` (enclosing route group), `App\Modules\Audit\API\Middleware\EnsureOwnerOrAdminRole` (route-group specific)
- Throttle: `api` rate limiter — 120 requests per minute, keyed by the authenticated user's ID (falls back to IP) (`AppServiceProvider::registerRateLimiters`)
- Authentication Guard: `api` (JWT — `config/auth.php` `guards.api.driver = 'jwt'`)
- Required Role: `OWNER` or `ADMIN` — enforced by `Audit\API\Middleware\EnsureOwnerOrAdminRole`, which checks `$user->role?->value` is one of `['OWNER', 'ADMIN']`; Member is explicitly excluded. Per that middleware's own doc-block: "This is the first Member-gets-403-on-a-GET decision in this entire series."

---

# Request

## Headers

- `Authorization: Bearer {token}` — required by the `auth:api` middleware.

## Path Parameters

- `auditLogId` — the Audit Log entry's ID.

## Query Parameters

None.

## Request Body

None.

## Validation Rules

Not found in the implementation — no Form Request is used for this endpoint.

---

# Processing

- Service: Not found in the implementation.
- DTO: Not found in the implementation.
- Action: `GetAuditLogAction::handle($organizationId, $auditLogId)`
- Business Rules:
  - `organization_id` is always server-derived from the authenticated Human's JWT (`ResolvesAuditListQuery::organizationId()`), never accepted from the request.
  - `AuditRepository::findById($auditLogId, $organizationId)` scopes the lookup to both `id` and `organization_id`.
  - If no matching entry is found, throws `AuditLogNotFoundException`. Per the exception's own doc-block: "Thrown for both a non-existent `audit_logs` entry and one belonging to a different Organization — indistinguishable by design (Security Through Obscurity), same discipline as every prior module."
- Database Operations: `AuditLog::query()->where('organization_id', ...)->where('id', ...)->first()` (`AuditRepository::findById()`).
- Events: Not found in the implementation.
- Jobs: Not found in the implementation.
- Notifications: Not found in the implementation.

---

# Response

## Success Status Code

200

## Response Resource

`App\Modules\Audit\Presentation\AuditLogResource`

## Response Structure

```
{
  "data": {
    "id": ...,
    "actor_type": ...,
    "actor_id": ...,
    "action": ...,
    "resource_type": ...,
    "resource_id": ...,
    "metadata": ...,
    "created_at": ...
  }
}
```

Per `AuditLogResource`'s own doc-block: this shape is "used for both the list item shape and the single-entry detail shape (identical ... an audit log entry has no large field like `raw_ases_json` to exclude from the list view)." `actor_id` is `null` for `actor_type: SYSTEM` entries (never fabricated).

---

# Error Responses

| Status | Condition |
|---------|-----------|
|401|No token provided, an invalid/expired token, or an Agent (API Key) with no JWT — rendered via the global `AuthenticationException` render callback (`bootstrap/app.php`), which reuses `AuthenticationFailedException::render()`: `error.code = AUTHENTICATION_FAILED`.|
|403|Caller's role is `MEMBER` (neither `OWNER` nor `ADMIN`) — rendered by `AuditForbiddenException::render()`: `error.code = FORBIDDEN`, `error.message = "You do not have permission to perform this action."`|
|404|No Audit Log entry exists with the given `auditLogId` in the caller's Organization (including an entry that exists but belongs to a different Organization) — rendered by `AuditLogNotFoundException::render()`: `error.code = NOT_FOUND`, `error.message = "Audit log entry not found."`|
|429|Rate limit exceeded — `throttle:api` (120 requests/minute per authenticated user). Rendered by Laravel's default throttle response (not customized for this route).|

Only include implemented responses.

---

# Side Effects

- Database Changes: Not found in the implementation.
- Audit Logs: Not applicable — this endpoint reads `audit_logs`, it does not write to it.
- Security Logs: Not found in the implementation.
- Events: Not found in the implementation.
- Cache: Not found in the implementation.
- Notifications: Not found in the implementation.

---

# Dependencies

- Models:
  - `App\Modules\Audit\Infrastructure\Persistence\AuditLog`
- Resources:
  - `App\Modules\Audit\Presentation\AuditLogResource`
- Services: Not found in the implementation. (Uses Action class: `GetAuditLogAction`, and `AuditRepository` for persistence access.)
- Policies: Not found in the implementation.
- Enums:
  - `App\Modules\Audit\Domain\ActorType` (`USER`, `SYSTEM` — `AGENT` deliberately absent, per `ADR-002-audit-scoped-to-human-initiated-actions.md`)
- Traits: Not found in the implementation (specific to this endpoint's own logic — `ResolvesAuditListQuery` is used by `index`, not `show`, since `show` takes no query parameters).

---

# Postman Collection Notes

## Authorization

Bearer token (`Authorization: Bearer {access_token}`) for a User whose `role` is `OWNER` or `ADMIN`.

## Variables To Save

Not found in the implementation.

## Pre-request Requirements

A valid `access_token` for an authenticated Owner- or Admin-role User, and an existing `auditLogId` within that User's Organization.

## Post-request Automation

Not found in the implementation.

## Suggested Test Cases

Existing coverage found in `backend/tests/Feature/Audit/AuditLogsTest.php`:

- Admin can view a single audit log entry.
- A non-existent audit log ID returns 404.
- System-actor audit entries have a `null` `actor_id`, never a fabricated one.
- A member cannot view an audit log entry (403).
- An owner cannot view another organization's audit log entry by ID (404, data isolation).

## Collection Folder

Not found in the implementation.

## Execution Order

Not found in the implementation.

---

# Related Endpoints

- `GET /v1/audit-logs`
- `GET /v1/security-logs`
