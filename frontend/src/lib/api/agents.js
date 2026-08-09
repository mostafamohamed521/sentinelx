// Agents API — matches the provided endpoint contract exactly:
//   GET    /v1/agents
//   POST   /v1/agents
//   GET    /v1/agents/{agentId}
//   PATCH  /v1/agents/{agentId}
//   PATCH  /v1/agents/{agentId}/archive
//   POST   /v1/agents/{agentId}/rotate-api-key
//
// No DELETE — archiving preserves agents for historical integrity.
// No reactivate — Agent archival is a deliberate, one-way transition
// (AgentPolicy has no reverse path, and no /agents/{id}/reactivate route
// exists).
//
// Every single-resource endpoint (GET/POST/PATCH one Agent, archive,
// rotate-api-key) wraps its payload in `{ data: ... }` on the real Backend;
// this module unwraps that envelope so callers always get the plain
// resource object back, in both MOCK_MODE and real mode. List endpoints
// keep the full `{ data, pagination }` envelope since callers need the
// pagination block too.
//
// GET /v1/agents/{agentId}/observations is NOT part of the provided
// endpoint contract for this pass (no OBSERVATIONS_API/AGENTS_API doc
// confirmed it) — kept as-is from the prior integration, unverified.

import { apiFetch, toQueryString, MOCK_MODE, ApiError } from "../apiClient.js";
import { db } from "../mockDb.js";

function delay(ms = 350) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

// GET /v1/agents — query params: status, page, per_page (no `sort`; the
// Backend always orders by created_at ascending and there is no documented
// way to change that).
export async function listAgents(params = {}) {
  if (MOCK_MODE) {
    await delay();
    return db.getAgents(params);
  }
  return apiFetch(`/agents${toQueryString(params)}`);
}

// POST /v1/agents — Owner only. Body: name, framework, framework_version?, description?
export async function createAgent(payload) {
  if (MOCK_MODE) {
    await delay();
    if (!payload.name) throw new ApiError("VALIDATION_ERROR", "Agent name is required", { field: "name" });
    if (!payload.framework) throw new ApiError("VALIDATION_ERROR", "Framework is required", { field: "framework" });
    return db.createAgent(payload);
  }
  const res = await apiFetch("/agents", { method: "POST", body: payload });
  return res.data;
}

// GET /v1/agents/{agentId}
export async function getAgent(agentId) {
  if (MOCK_MODE) {
    await delay();
    const agent = db.getAgent(agentId);
    if (!agent) throw new ApiError("NOT_FOUND", "Agent not found.");
    return agent;
  }
  const res = await apiFetch(`/agents/${agentId}`);
  return res.data;
}

// PATCH /v1/agents/{agentId} — Owner only. Body: any of name, framework,
// framework_version, description (at least one required).
export async function updateAgent(agentId, payload) {
  if (MOCK_MODE) {
    await delay();
    return db.updateAgent(agentId, payload);
  }
  const res = await apiFetch(`/agents/${agentId}`, { method: "PATCH", body: payload });
  return res.data;
}

// PATCH /v1/agents/{agentId}/archive — Owner only, one-way.
// Response is a partial resource: { id, status, updated_at } only.
export async function archiveAgent(agentId) {
  if (MOCK_MODE) {
    await delay();
    return db.archiveAgent(agentId);
  }
  const res = await apiFetch(`/agents/${agentId}/archive`, { method: "PATCH" });
  return res.data;
}

// POST /v1/agents/{agentId}/rotate-api-key — Owner only.
// Response: { key_prefix, raw_key, status, created_at }. `raw_key` is shown
// exactly once and is never retrievable again afterward.
export async function rotateApiKey(agentId) {
  if (MOCK_MODE) {
    await delay();
    return db.rotateApiKey(agentId);
  }
  const res = await apiFetch(`/agents/${agentId}/rotate-api-key`, { method: "POST" });
  return res.data;
}

// GET /v1/agents/{agentId}/observations — Observation-owned despite the
// /agents URL prefix; shares ObservationSummaryResource/ObservationCollection
// with GET /v1/observations. Query params: page, per_page only (no
// analysis_status filter here, unlike the general list endpoint).
export async function listAgentObservations(agentId, params = {}) {
  if (MOCK_MODE) {
    await delay();
    return db.getObservations({ ...params, agent_id: agentId });
  }
  return apiFetch(`/agents/${agentId}/observations${toQueryString(params)}`);
}
