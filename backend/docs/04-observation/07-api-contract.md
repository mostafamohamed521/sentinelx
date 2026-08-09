# 07 — Observation API Contract

> Expands [`docs.zip/09-api-reference/04-OBSERVATIONS_API.md`](../docs/docs/09-api-reference/04-OBSERVATIONS_API.md) into an implementation-ready contract, plus the one endpoint declared in `docs/backend/agent/06-api-contract.md` §7 as Observation-owned. Response envelope, error shape, and pagination shape follow [`09-API_CONVENTIONS.md`](../docs/docs/09-api-reference/09-API_CONVENTIONS.md), [`07-ERROR_CODES.md`](../docs/docs/09-api-reference/07-ERROR_CODES.md), and [`08-PAGINATION.md`](../docs/docs/09-api-reference/08-PAGINATION.md) exactly.

---

## 0. Conventions Recap

```text
Base path:      /api/v1
Content-Type:    application/json
Timestamps:      ISO 8601, UTC
Error shape:     { "error": { "code": "...", "message": "...", "details": {} } }
```

---

## 1. `POST /api/v1/observations`

**Owner:** Observation module · **Auth:** API Key (Agent) only · **Role:** N/A (Agents have no Roles)

**Request** — raw ASES JSON body (see [`02-domain.md`](./02-domain.md) §2 and [`04-validation.md`](./04-validation.md) for the exact shape validated)
```json
{
  "context": {
    "framework": "CrewAI",
    "agent_version": "1.2.0",
    "environment": "production",
    "execution_start_time": "2026-07-29T09:59:50Z",
    "execution_finish_time": "2026-07-29T10:00:00Z"
  },
  "events": [
    {
      "header": { "event_type": "api_call", "timestamp": "2026-07-29T09:59:52Z" },
      "payload": { "url": "https://api.example.com/v1/data", "method": "GET" }
    }
  ],
  "metadata": {
    "spec_version": "1.0",
    "sdk_version": "0.4.1",
    "generated_at": "2026-07-29T10:00:00Z"
  }
}
```

**Response `202 Accepted`**
```json
{
  "data": {
    "id": "0198c3a1-...",
    "received_at": "2026-07-29T10:00:01Z",
    "analysis_status": "PENDING"
  }
}
```

**Errors**
| HTTP | Code | Cause |
|------|------|-------|
| 400 | `BAD_REQUEST` | Request body is not valid JSON at all |
| 401 | `UNAUTHORIZED` | Missing/invalid/revoked API Key, or Archived Agent (see [`02-auth/contracts/auth-errors.md`](../02-auth/02-auth/contracts/auth-errors.md)) |
| 422 | `VALIDATION_ERROR` | Fails any of the 5 structural checks in [`04-validation.md`](./04-validation.md) §3 |

**Never returns:** any field describing risk, verdict, or analysis — none exists yet at submission time.

---

## 2. `GET /api/v1/observations`

**Owner:** Observation module · **Auth:** JWT (Human) · **Role:** Owner, Admin, Member

Returns all Observations belonging to the authenticated Organization, paginated, most recent first.

**Query Parameters**
```text
page             integer, optional, default 1
per_page         integer, optional, default 20
agent_id         string (UUID), optional — filter to a single Agent
analysis_status  string, optional — PENDING | PROCESSING | COMPLETED | FAILED
```

**Response `200 OK`**
```json
{
  "data": [
    {
      "id": "0198c3a1-...",
      "agent_id": "0198a1b2-...",
      "analysis_status": "PENDING",
      "received_at": "2026-07-29T10:00:01Z",
      "created_at": "2026-07-29T10:00:01Z"
    }
  ],
  "pagination": {
    "page": 1,
    "per_page": 20,
    "total_items": 42,
    "total_pages": 3
  }
}
```

**Note:** the list response deliberately omits `raw_ases_json` — a full ASES payload can be large, and a list view has no need for it. Full detail is only ever returned by §3.

---

## 3. `GET /api/v1/observations/{observationId}`

**Owner:** Observation module (Stage 3 scope) · **Auth:** JWT (Human) · **Role:** Owner, Admin, Member

**Response `200 OK`**
```json
{
  "data": {
    "id": "0198c3a1-...",
    "agent_id": "0198a1b2-...",
    "organization_id": "0198a0f0-...",
    "analysis_status": "PENDING",
    "raw_ases_json": { "context": { "...": "..." }, "events": [ "..." ], "metadata": { "...": "..." } },
    "received_at": "2026-07-29T10:00:01Z",
    "processing_started_at": null,
    "processed_at": null,
    "created_at": "2026-07-29T10:00:01Z",
    "updated_at": "2026-07-29T10:00:01Z",
    "prediction": null
  }
}
```

> **`prediction` is always `null` as of Stage 3.** This field exists in the contract now so the response shape never has to change once Analysis ships in Stage 4 — see [`adr/ADR-003-prediction-composition-deferred.md`](./adr/ADR-003-prediction-composition-deferred.md). Whoever implements Stage 4 populates this field by composing on top of this module's `ObservationLookupContract`; nothing about this endpoint's Observation-side implementation changes when that happens.

**Errors**
| HTTP | Code | Cause |
|------|------|-------|
| 404 | `NOT_FOUND` | Observation doesn't exist, or belongs to a different Organization |

---

## 4. `GET /api/v1/agents/{agentId}/observations`

**Owner:** Observation module (confirmed in [`docs/backend/agent/06-api-contract.md`](../03-agent/06-api-contract.md) §7) · **Auth:** JWT (Human) · **Role:** Owner, Admin, Member

Same response shape as §2's list, filtered to a single Agent — functionally equivalent to `GET /observations?agent_id={agentId}`, offered as a nested convenience route matching the Agent module's own URL grouping.

**Response `200 OK`** — same shape as §2.

**Errors**
| HTTP | Code | Cause |
|------|------|-------|
| 404 | `NOT_FOUND` | `agentId` doesn't exist, or belongs to a different Organization |

**Implementation note:** this Controller still lives inside the Observation module (per Agent module's own `01-overview.md` §5 routing table), but the `agentId` path parameter must be validated against the Agent module's own `AgentLookupContract` (read-only, same contract Authentication already consumes in Stage 2) before querying Observations — never a raw, unchecked `WHERE agent_id = ?` with no existence/ownership check.

---

## 5. Endpoint Ownership Summary

```text
Observation Module                                Deferred to Analysis (Stage 4)
────────────────────────                          ───────────────────────────────
POST   /observations
GET    /observations
GET    /observations/{id}   (Observation fields)   GET /observations/{id}   (prediction field)
GET    /agents/{id}/observations
```
