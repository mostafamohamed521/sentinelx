# Endpoint Inspection Report

---

# Endpoint

- Method: GET
- Route: `/v1/dashboard`
- Endpoint Name: Dashboard Snapshot
- Purpose: Returns an aggregated snapshot of organization-wide stats, recent Agents/Observations/Alerts, and a risk (verdict) distribution — composed entirely from other modules' read contracts.

---

# Routing

- API Version: v1
- Controller: `App\Modules\Dashboard\API\Controllers\DashboardController`
- Controller Method: `show`
- Middleware: `auth:api`, `throttle:api` (applied to the enclosing route group in `routes/api.php`)
- Throttle: `api` rate limiter — 120 requests per minute, keyed by the authenticated user's ID (falls back to IP) (`AppServiceProvider::registerRateLimiters`)
- Authentication Guard: `api` (JWT — `config/auth.php` `guards.api.driver = 'jwt'`)
- Required Role: None — per the controller's own doc-block, "No Role gate beyond 'is an authenticated Human' — Owner, Admin, and Member all have identical access."

---

# Request

## Headers

- `Authorization: Bearer {token}` — required by the `auth:api` middleware.

## Path Parameters

None.

## Query Parameters

None.

## Request Body

None.

## Validation Rules

Not found in the implementation — no Form Request is used for this endpoint.

---

# Processing

- Service: Not found in the implementation.
- DTO: `App\Modules\Dashboard\Domain\DashboardSnapshot` — per its own doc-block, "a plain, non-persisted value object — not an Eloquent model, not backed by any table ... this module owns no data." A `final readonly class` holding `totalAgents`, `activeAgentCount`, `totalObservationsLast30Days`, `openAlerts`, `activeAgents`, `recentObservations`, `recentAlerts`, `riskSummary`.
- Action: `GetDashboardSnapshotAction::handle($organizationId)`
- Business Rules:
  - `organization_id` is always server-derived from the authenticated Human's JWT (`DashboardController`), never accepted from the request.
  - Per the Action's own doc-block: "Calls all four contracts and assembles `DashboardSnapshot` — no business logic of its own beyond assembly ... Never queries agents/observations/predictions/alerts directly."
  - The four consumed contracts: `AgentSummaryContract`, `ObservationSummaryContract`, `PredictionStatsContract`, `AlertSummaryContract`.
  - `RECENT_ITEMS_LIMIT = 5` — a fixed limit for "recent" lists (Active Agents, Recent Observations, Recent Alerts); per the Action's own doc-block, "a small, fixed limit for a dashboard summary widget, not a substitute for the full paginated list endpoints — freely adjustable, not a frozen business rule."
  - `OBSERVATION_WINDOW_DAYS = 30` — the trailing window for `totalObservationsLast30Days`, computed as `now()->subDays(30)`.
  - `openAlerts` is read from `AlertSummaryContract::countByStatusForOrganization()`'s `OPEN` key specifically.
  - `riskSummary` groups by Prediction verdict (`SAFE`/`SUSPICIOUS`/`MALICIOUS`), not by Alert severity — per `PredictionStatsContract`'s own doc-block, "per `ADR-002-risk-summary-by-verdict-not-severity.md`. Covers every analyzed Observation, SAFE included."
- Database Operations: Not found directly in this module — all reads happen behind the four contracts (`AgentSummaryContract`, `ObservationSummaryContract`, `PredictionStatsContract`, `AlertSummaryContract`), each implemented by its own module's repository.
- Events: Not found in the implementation.
- Jobs: Not found in the implementation.
- Notifications: Not found in the implementation.

---

# Response

## Success Status Code

200

## Response Resource

`App\Modules\Dashboard\Presentation\DashboardResource`

## Response Structure

```
{
  "data": {
    "organization_stats": {
      "total_agents": ...,
      "active_agents": ...,
      "total_observations_last_30_days": ...,
      "open_alerts": ...
    },
    "active_agents": [
      { "id": ..., "name": ..., "status": ..., "last_seen_at": ... }
    ],
    "recent_observations": [
      { "id": ..., "agent_id": ..., "analysis_status": ..., "received_at": ... }
    ],
    "recent_alerts": [
      { "id": ..., "severity": ..., "status": ..., "created_at": ..., "reasons": [...] }
    ],
    "risk_summary": { "SAFE": ..., "SUSPICIOUS": ..., "MALICIOUS": ... }
  }
}
```

Per `DashboardResource`'s own doc-block: "Deliberately minimal field selection per embedded list — narrower than `AgentResource`/`ObservationSummaryResource`/`AlertSummaryResource`, matching the documented response exactly rather than reusing those resources wholesale (which would leak extra fields never specified for this summary endpoint)." The `reasons` field on each `recent_alerts` entry is read off the eager-loaded `prediction` relation.

---

# Error Responses

| Status | Condition |
|---------|-----------|
|401|No token provided, an invalid/expired token, or an Agent (API Key) with no JWT — rendered via the global `AuthenticationException` render callback (`bootstrap/app.php`), which reuses `AuthenticationFailedException::render()`: `error.code = AUTHENTICATION_FAILED`.|
|429|Rate limit exceeded — `throttle:api` (120 requests/minute per authenticated user). Rendered by Laravel's default throttle response (not customized for this route).|

Only include implemented responses.

---

# Side Effects

- Database Changes: Not found in the implementation.
- Audit Logs: Not found in the implementation.
- Security Logs: Not found in the implementation.
- Events: Not found in the implementation.
- Cache: Not found in the implementation.
- Notifications: Not found in the implementation.

---

# Dependencies

- Models:
  - `App\Modules\Agent\Infrastructure\Persistence\Agent` (returned by `AgentSummaryContract`, not queried directly by this module)
  - `App\Modules\Observation\Infrastructure\Persistence\Observation` (returned by `ObservationSummaryContract`, not queried directly)
  - `App\Modules\Alert\Infrastructure\Persistence\Alert` (returned by `AlertSummaryContract`, not queried directly)
- Resources:
  - `App\Modules\Dashboard\Presentation\DashboardResource`
- Services: Not found in the implementation. (Uses Action class: `GetDashboardSnapshotAction`.)
- Policies: Not found in the implementation.
- Enums: Not found in the implementation (specific to this endpoint's own logic — `riskSummary` keys `SAFE`/`SUSPICIOUS`/`MALICIOUS` are Analysis's `Verdict` enum values, not owned by this module).
- Traits: Not found in the implementation (specific to this endpoint's own logic).
- Other (cross-module read contracts consumed):
  - `App\Modules\Agent\Application\Contracts\AgentSummaryContract`
  - `App\Modules\Observation\Application\Contracts\ObservationSummaryContract`
  - `App\Modules\Analysis\Application\Contracts\PredictionStatsContract`
  - `App\Modules\Alert\Application\Contracts\AlertSummaryContract`

---

# Postman Collection Notes

## Authorization

Bearer token (`Authorization: Bearer {access_token}`), obtained from `POST /v1/auth/login`.

## Variables To Save

Not found in the implementation.

## Pre-request Requirements

A valid `access_token` for an authenticated User must be available.

## Post-request Automation

Not found in the implementation.

## Suggested Test Cases

Existing coverage found in `backend/tests/Feature/Dashboard/DashboardTest.php`:

- `GET /dashboard` returns all five sections, correctly populated, from real data.
- A brand-new, empty organization returns a well-formed response with zeros and empty arrays.
- An organization with fewer than 5 recent items returns exactly that many, not padded.
- An organization with more than 5 recent observations still returns only 5.
- `risk_summary` includes a zero count for a verdict that has never occurred for this organization.
- Changing only `AgentSummaryContract` changes only agent-derived fields (field-provenance test).
- Changing only `ObservationSummaryContract` changes only the observation-derived field.
- Changing only `PredictionStatsContract` changes only `risk_summary`.
- Changing only `AlertSummaryContract` changes only alert-derived fields.
- `organization_id` is passed identically to all four contract calls.
- Owner, Admin, and Member can each independently view the dashboard.
- An unauthenticated request cannot view the dashboard (401).
- An Agent (API Key) cannot view the dashboard (401).
- Organization A's dashboard never includes Organization B's data (data isolation).

## Collection Folder

Not found in the implementation.

## Execution Order

Not found in the implementation.

---

# Related Endpoints

- `GET /v1/agents`
- `GET /v1/observations`
- `GET /v1/alerts`
