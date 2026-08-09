# Endpoint Inspection Report

---

# Endpoint

- Method: GET
- Route: `/v1/audit-logs`
- Endpoint Name: List Audit Logs
- Purpose: Returns a paginated, filterable list of Audit Log entries for the caller's own Organization. Owner and Admin only.

---

# Routing

- API Version: v1
- Controller: `App\Modules\Audit\API\Controllers\AuditController`
- Controller Method: `index`
- Middleware: `auth:api`, `throttle:api` (enclosing route group), `App\Modules\Audit\API\Middleware\EnsureOwnerOrAdminRole` (route-group specific)
- Throttle: `api` rate limiter — 120 requests per minute, keyed by the authenticated user's ID (falls back to IP) (`AppServiceProvider::registerRateLimiters`)
- Authentication Guard: `api` (JWT — `config/auth.php` `guards.api.driver = 'jwt'`)
- Required Role: `OWNER` or `ADMIN` — enforced by `Audit\API\Middleware\EnsureOwnerOrAdminRole`, which checks `$user->role?->value` is one of `['OWNER', 'ADMIN']`; Member is explicitly excluded. Per the middleware's own doc-block: "A dedicated copy rather than reusing another module's middleware: Audit depends on nothing and nothing depends on Audit, so it must never import another module's Infrastructure/Domain layer."

---

# Request

## Headers

- `Authorization: Bearer {token}` — required by the `auth:api` middleware.

## Path Parameters

None.

## Query Parameters

- `actor_id` — optional; filters to a single actor's ID.
- `action` — optional; filters to an exact `action` string (e.g. `agent.created`).
- `resource_type` — optional; filters to an exact `resource_type` string (e.g. `Agent`).
- `from` — optional; parsed via `Carbon::parse()`, filters `created_at >= from`.
- `to` — optional; parsed via `Carbon::parse()`, filters `created_at <= to`.
- `page` — optional; defaults to `1` (`$request->integer('page', 1)`).
- `per_page` — optional; defaults to `20`, clamped to a maximum of `100` (`Controller::perPage()`, `MAX_PER_PAGE = 100`).

## Request Body

None.

## Validation Rules

Not found in the implementation — no Form Request is used for this endpoint; query parameters are read and coerced directly in the controller (via the shared `ResolvesAuditListQuery` trait, also used by `SecurityLogController`).

---

# Processing

- Service: Not found in the implementation.
- DTO: Not found in the implementation.
- Action: `ListAuditLogsAction::handle(organizationId, actorId, action, resourceType, from, to, perPage, page)`, which delegates to `AuditRepository::listForOrganization()` with a fixed `actionIn: null`.
- Business Rules:
  - `organization_id` is always server-derived from the authenticated Human's JWT (`ResolvesAuditListQuery::organizationId()`), never accepted from the request.
  - Results are scoped with `where('organization_id', $organizationId)`.
  - Each of `actor_id`, `action`, `resource_type`, `from`, `to` is applied as an additional `where`/range filter only when provided.
  - `actionIn` is explicitly `null` for this endpoint (unfiltered by a fixed action set) — per `AuditRepository`'s own comment, this differs from `GetSecurityLogsAction`, which passes a fixed `actionIn` list (or `[]` to mean "match nothing") over the exact same query path, per `ADR-003-security-logs-is-filtered-audit-view.md`.
  - Results are ordered by `created_at` descending.
  - `audit_logs` rows are write-once — per `AuditRepository`'s own doc-block, "No `update()`/`delete()` method exists here, deliberately."
- Database Operations: `AuditLog::query()->where('organization_id', ...)->when($actorId, ...)->when($action, ...)->when($resourceType, ...)->when($from, ...)->when($to, ...)->orderByDesc('created_at')->paginate(...)` (`AuditRepository::listForOrganization()`).
- Events: Not found in the implementation.
- Jobs: Not found in the implementation.
- Notifications: Not found in the implementation.

---

# Response

## Success Status Code

200

## Response Resource

`App\Modules\Audit\Presentation\AuditLogCollection` (wraps `AuditLogResource`)

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

Per `AuditLogResource`'s own doc-block: "used for both the list item shape and the single-entry detail shape (identical, unlike Agent/Observation's list-vs-detail split — an audit log entry has no large field like `raw_ases_json` to exclude from the list view)." `actor_id` is `null` for `actor_type: SYSTEM` entries (never fabricated).

---

# Error Responses

| Status | Condition |
|---------|-----------|
|401|No token provided, an invalid/expired token, or an Agent (API Key) with no JWT — rendered via the global `AuthenticationException` render callback (`bootstrap/app.php`), which reuses `AuthenticationFailedException::render()`: `error.code = AUTHENTICATION_FAILED`.|
|403|Caller's role is `MEMBER` (neither `OWNER` nor `ADMIN`) — rendered by `AuditForbiddenException::render()`.|
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
  - `App\Modules\Audit\Presentation\AuditLogCollection`
  - `App\Modules\Audit\Presentation\AuditLogResource`
- Services: Not found in the implementation. (Uses Action class: `ListAuditLogsAction`, and `AuditRepository` for persistence access.)
- Policies: Not found in the implementation.
- Enums:
  - `App\Modules\Audit\Domain\ActorType` (`USER`, `SYSTEM` — `AGENT` deliberately absent, per `ADR-002-audit-scoped-to-human-initiated-actions.md`)
- Traits:
  - `App\Modules\Audit\API\Controllers\Concerns\ResolvesAuditListQuery` (shared with `SecurityLogController`; provides `organizationId()` and `dateQuery()`)

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

Existing coverage found in `backend/tests/Feature/Audit/AuditLogsTest.php`:

- Owner can list audit logs for their organization.
- Audit logs can be filtered by `actor_id`, `action`, and `resource_type`.
- `audit_logs` rows can never be updated or deleted — no such route exists (no `PUT`/`PATCH`/`DELETE`/`POST`).
- System-actor audit entries have a `null` `actor_id`, never a fabricated one.
- A member cannot list or view audit logs (403).
- An admin can list and view audit logs — confirms Admin is not excluded, only Member.
- An unauthenticated request cannot access audit logs (401).
- An Agent (API Key) cannot access audit logs (401).
- Organization A's audit logs never include Organization B's entries (data isolation).

## Collection Folder

Not found in the implementation.

## Execution Order

Not found in the implementation.

---

# Related Endpoints

- `GET /v1/audit-logs/{auditLogId}`
- `GET /v1/security-logs`
