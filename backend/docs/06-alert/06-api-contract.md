# 06 — Alert API Contract

> Expands [`docs.zip/09-api-reference/05-ALERTS_API.md`](../docs/docs/09-api-reference/05-ALERTS_API.md) into an implementation-ready contract — exactly the four endpoints that document defines, no more (see [`adr/ADR-002-no-reopen-endpoint-in-v1.md`](./adr/ADR-002-no-reopen-endpoint-in-v1.md)). Response envelope, error shape, and pagination shape follow [`09-API_CONVENTIONS.md`](../docs/docs/09-api-reference/09-API_CONVENTIONS.md), [`07-ERROR_CODES.md`](../docs/docs/09-api-reference/07-ERROR_CODES.md), and [`08-PAGINATION.md`](../docs/docs/09-api-reference/08-PAGINATION.md) exactly.

---

## 0. Conventions Recap

```text
Base path:      /api/v1
Auth:            Bearer JWT (Human) — required on every endpoint in this file; no Agent access
Error shape:     { "error": { "code": "...", "message": "...", "details": {} } }
```

---

## 1. `GET /api/v1/alerts`

**Owner:** Alert module · **Role:** Owner, Admin, Member

**Query Parameters**
```text
page       integer, optional, default 1
per_page   integer, optional, default 20
status     string, optional — OPEN | ACKNOWLEDGED | RESOLVED
severity   string, optional — LOW | MEDIUM | HIGH | CRITICAL
```

**Response `200 OK`**
```json
{
  "data": [
    {
      "id": "0198c5a0-...",
      "prediction_id": "0198c4b2-...",
      "severity": "HIGH",
      "status": "OPEN",
      "created_at": "2026-07-29T10:00:10Z"
    }
  ],
  "pagination": {
    "page": 1,
    "per_page": 20,
    "total_items": 7,
    "total_pages": 1
  }
}
```

The list view is deliberately minimal — it excludes the related Observation/Prediction detail, matching the same list-vs-detail pattern already used in Agent and Observation.

---

## 2. `GET /api/v1/alerts/{alertId}`

**Owner:** Alert module · **Role:** Owner, Admin, Member

Per [`ALERTS_API.md`](../docs/docs/09-api-reference/05-ALERTS_API.md): *"Returns complete Alert details including the related Observation and Prediction."*

**Response `200 OK`**
```json
{
  "data": {
    "id": "0198c5a0-...",
    "severity": "HIGH",
    "status": "OPEN",
    "acknowledged_at": null,
    "acknowledged_by": null,
    "resolved_at": null,
    "resolved_by": null,
    "created_at": "2026-07-29T10:00:10Z",
    "updated_at": "2026-07-29T10:00:10Z",
    "prediction": {
      "id": "0198c4b2-...",
      "verdict": "MALICIOUS",
      "confidence": 0.91,
      "risk_score": 88,
      "summary": "Command execution matching a known exfiltration pattern.",
      "model_version": "sentinelx-ml-1.4.2",
      "analyzed_at": "2026-07-29T10:00:09Z"
    },
    "observation": {
      "id": "0198c3a1-...",
      "agent_id": "0198a1b2-...",
      "received_at": "2026-07-29T10:00:01Z"
    }
  }
}
```

**Implementation note — a normal, permitted composition, not a repeat of the deferred pattern:** unlike `GET /observations/{id}`'s Stage 3/4 split, this composition doesn't need to be deferred or split across modules with a placeholder field, because `Alert → Analysis → Observation` is entirely downward through the dependency chain — this Controller, living in the Alert module, is allowed to call both `PredictionLookupContract` (Analysis) and, transitively via Analysis's own already-established pattern, the Observation data needed. Concretely: `AlertController@show` calls `PredictionLookupContract::findById()` (a small addition to that contract, analogous to `findByObservationId`) to get the Prediction, and separately calls `Observation\Application\Contracts\ObservationLookupContract::findByIdForOrganization()` (already exposed since Stage 3) directly — both are permitted, since Alert sits above both in the chain.

**Errors**
| HTTP | Code | Cause |
|------|------|-------|
| 404 | `NOT_FOUND` | Alert doesn't exist, or belongs to a different Organization |

---

## 3. `PATCH /api/v1/alerts/{alertId}/acknowledge`

**Owner:** Alert module · **Role:** Owner, Admin, Member

No meaningful request body (no optional note/comment field exists in any frozen document — not built here).

**Response `200 OK`**
```json
{
  "data": {
    "id": "0198c5a0-...",
    "status": "ACKNOWLEDGED",
    "acknowledged_at": "2026-07-29T10:15:00Z",
    "acknowledged_by": "0198a0e1-..."
  }
}
```

**Errors**
| HTTP | Code | Cause |
|------|------|-------|
| 404 | `NOT_FOUND` | Cross-tenant or non-existent Alert |
| 409 | `CONFLICT` | Alert is already `ACKNOWLEDGED` or `RESOLVED` (idempotency — see [`02-domain.md`](./02-domain.md) §5) |

---

## 4. `PATCH /api/v1/alerts/{alertId}/resolve`

**Owner:** Alert module · **Role:** Owner, Admin, Member

No meaningful request body, same reasoning as §3.

**Response `200 OK`**
```json
{
  "data": {
    "id": "0198c5a0-...",
    "status": "RESOLVED",
    "resolved_at": "2026-07-29T10:20:00Z",
    "resolved_by": "0198a0e1-..."
  }
}
```

Callable from either `OPEN` or `ACKNOWLEDGED` (skip-ahead allowed — see [`03-generation-pipeline.md`](./03-generation-pipeline.md) §4).

**Errors**
| HTTP | Code | Cause |
|------|------|-------|
| 404 | `NOT_FOUND` | Cross-tenant or non-existent Alert |
| 409 | `CONFLICT` | Alert is already `RESOLVED` |

---

## 5. What's Deliberately Not Here

```text
POST /alerts               — never; Alerts are system-generated only, per
                                ALERTS_API.md: "Alerts are never created directly by users."
PATCH /alerts/{id}/reopen   — not built in V1; see adr/ADR-002-no-reopen-endpoint-in-v1.md
DELETE /alerts/{id}         — never, per platform-wide convention (no DELETE on business
                                entities in V1)
```

---

## 6. Endpoint Ownership Summary

```text
Alert Module
────────────────────────
GET    /alerts
GET    /alerts/{id}
PATCH  /alerts/{id}/acknowledge
PATCH  /alerts/{id}/resolve
```

All four endpoints are fully owned and implemented by this module — no deferred composition, no route grouping across modules, unlike every stage before it.
