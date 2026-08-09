# 06 — Dashboard API Contract

> Expands [`docs.zip/09-api-reference/06-DASHBOARD_API.md`](../docs/docs/09-api-reference/06-DASHBOARD_API.md) into an implementation-ready contract — the single endpoint it defines, with a concrete shape for its five documented sections (*"Organization statistics, Recent Alerts, Recent Observations, Risk distribution, Active Agents"*).

---

## 0. Conventions Recap

```text
Base path:      /api/v1
Auth:            Bearer JWT (Human) — no Agent access
Error shape:     { "error": { "code": "...", "message": "...", "details": {} } }
```

---

## 1. `GET /api/v1/dashboard`

**Owner:** Dashboard module · **Role:** Owner, Admin, Member

No query parameters — the response always reflects the caller's own Organization, in full, with no filtering options (per [`03-scope-resolution.md`](./03-scope-resolution.md), filtering already exists on the underlying list endpoints, not here).

**Response `200 OK`**
```json
{
  "data": {
    "organization_stats": {
      "total_agents": 12,
      "active_agents": 9,
      "total_observations_last_30_days": 4820,
      "open_alerts": 3
    },
    "active_agents": [
      {
        "id": "0198a1b2-...",
        "name": "Support Agent",
        "status": "ACTIVE",
        "last_seen_at": "2026-07-29T09:58:11Z"
      }
    ],
    "recent_observations": [
      {
        "id": "0198c3a1-...",
        "agent_id": "0198a1b2-...",
        "analysis_status": "COMPLETED",
        "received_at": "2026-07-29T10:00:01Z"
      }
    ],
    "recent_alerts": [
      {
        "id": "0198c5a0-...",
        "severity": "HIGH",
        "status": "OPEN",
        "created_at": "2026-07-29T10:00:10Z"
      }
    ],
    "risk_summary": {
      "SAFE": 4512,
      "SUSPICIOUS": 285,
      "MALICIOUS": 23
    }
  }
}
```

**Field-by-field source, per [`04-aggregation-contracts.md`](./04-aggregation-contracts.md):**
```text
organization_stats.total_agents                   ← AgentSummaryContract::countTotalForOrganization()
organization_stats.active_agents                    ← AgentSummaryContract::countActiveForOrganization()
organization_stats.total_observations_last_30_days    ← ObservationSummaryContract::countForOrganizationSince()
organization_stats.open_alerts                          ← AlertSummaryContract::countByStatusForOrganization()['OPEN']
active_agents[]                                            ← AgentSummaryContract::listRecentlyActiveForOrganization()
recent_observations[]                                        ← ObservationSummaryContract::listRecentForOrganization()
recent_alerts[]                                                ← AlertSummaryContract::listRecentForOrganization()
risk_summary                                                     ← PredictionStatsContract::verdictDistributionForOrganization()
```

**List sizes** (`active_agents`, `recent_observations`, `recent_alerts`): capped at a small, fixed limit — **5 items each** — since this endpoint is a dashboard snapshot, not a substitute for the full paginated list endpoints. No frozen document specifies this exact number; 5 is a reasonable default for a summary widget, flagged here the same way other genuinely unspecified numbers have been flagged throughout this series (see [`docs/backend/alert/adr/ADR-001-severity-threshold-mapping.md`](../06-alert/adr/ADR-001-severity-threshold-mapping.md) for the precedent) — freely adjustable, not a frozen business rule.

---

## 2. Errors

```text
401 UNAUTHORIZED   — missing/invalid JWT
```

No other error case exists for this endpoint — there is no resource-specific `404` (see [`05-authorization.md`](./05-authorization.md) §3), and no request body means no `422` either. If any one of the four underlying contract calls fails unexpectedly (a genuine infrastructure error, not a business case), that surfaces as a normal `500`, handled by the platform's already-frozen Global Exception Handler — no special-case error handling is invented for this endpoint specifically.

---

## 3. Why No Pagination Envelope

Unlike every list endpoint in this backend, `GET /dashboard`'s response is a single, bounded object, not a collection — per [`08-PAGINATION.md`](../docs/docs/09-api-reference/08-PAGINATION.md)'s own scope (pagination applies to list endpoints), no `pagination` block appears anywhere in this response, and none of its embedded arrays (`active_agents`, `recent_observations`, `recent_alerts`) are paginated either — they're fixed-size summaries, not full lists (see §1's note on list sizes).
