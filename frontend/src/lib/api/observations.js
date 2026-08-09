// Observations API — matches the provided endpoint contract exactly:
//   GET  /v1/observations
//   GET  /v1/observations/{observationId}
//   (GET /v1/agents/{agentId}/observations lives in lib/api/agents.js since
//    it's routed under /agents, sharing the same ObservationSummaryResource)
//
// POST /v1/observations is Agent (API Key) only — authenticated with
// `X-API-Key`, not a User's Bearer JWT — so it is intentionally not exposed
// here; this dashboard is a Human-facing app and never submits Observations
// itself, only reads them.
//
// ObservationSummaryResource (both list endpoints) is deliberately minimal:
// { id, agent_id, analysis_status, received_at, created_at } — no verdict,
// confidence, or risk_score. Those only appear nested under `prediction` on
// the single-resource ObservationResource, and only once analysis_status
// is COMPLETED (prediction stays null for PENDING/PROCESSING/FAILED).

import { apiFetch, toQueryString, MOCK_MODE, ApiError } from "../apiClient.js";
import { db } from "../mockDb.js";

function delay(ms = 350) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

// GET /v1/observations — query params: agent_id, analysis_status, page, per_page.
export async function listObservations(params = {}) {
  if (MOCK_MODE) {
    await delay();
    return db.getObservations(params);
  }
  return apiFetch(`/observations${toQueryString(params)}`);
}

// GET /v1/observations/{observationId} — full ObservationResource, including
// raw_ases_json and the embedded `prediction` (null until analysis completes).
export async function getObservation(observationId) {
  if (MOCK_MODE) {
    await delay();
    const obs = db.getObservation(observationId);
    if (!obs) throw new ApiError("NOT_FOUND", "Observation not found.");
    return obs;
  }
  const res = await apiFetch(`/observations/${observationId}`);
  return res.data;
}
