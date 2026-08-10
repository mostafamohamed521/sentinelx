// In-memory mock "database" backing every mock API module under src/lib/api/.
// Unlike static mockData.js (removed — it duplicated this data and was
// never imported), this supports real mutations (archive an agent,
// acknowledge an alert, rotate a key) that persist for the rest of the
// session, and real pagination/filtering — so the pages behave the same
// shape as they will against the real Backend.
//
// Agent/Alert/Prediction field names and enum values below match the
// provided endpoint contract exactly (AgentResource, AlertSummaryResource /
// AlertDetailResource, PredictionDetailResource, AuditLogResource) —
// including the real Backend's uppercase status/severity/role enums.
// Anything the contract does NOT expose on a resource (e.g. an Agent's
// risk_level, an Alert's agent_name) is deliberately absent here too, so a
// real mismatch would surface instead of being silently masked by the mock.

let agents = [
  {
    id: "agt_789", name: "Finance Assistant", framework: "CrewAI", framework_version: "1.2.0",
    description: "Reviews invoices and generates financial reports.",
    status: "ACTIVE", last_seen_at: "2026-07-25T09:45:00Z",
    created_at: "2026-06-01T09:00:00Z", updated_at: "2026-07-25T09:45:00Z",
  },
  {
    id: "agt_790", name: "Support Agent", framework: "LangChain", framework_version: "0.9.4",
    description: "Handles customer support tickets and refunds.",
    status: "ACTIVE", last_seen_at: "2026-07-25T10:02:00Z",
    created_at: "2026-06-03T09:00:00Z", updated_at: "2026-07-25T10:02:00Z",
  },
  {
    id: "agt_791", name: "Sales Agent", framework: "CrewAI", framework_version: "1.1.0",
    description: "Qualifies leads and drafts outbound emails.",
    status: "ACTIVE", last_seen_at: "2026-07-25T08:30:00Z",
    created_at: "2026-06-10T09:00:00Z", updated_at: "2026-07-25T08:30:00Z",
  },
  {
    id: "agt_792", name: "Research Agent", framework: "Custom", framework_version: "0.4.1",
    description: "Summarizes market research documents.",
    status: "ARCHIVED", last_seen_at: "2026-06-01T00:00:00Z",
    created_at: "2026-05-01T09:00:00Z", updated_at: "2026-06-01T00:00:00Z",
  },
];

// Observations are NOT part of the provided endpoint contract for this
// pass (no OBSERVATIONS_API doc confirmed their shape) — left as-is from a
// prior integration attempt, unverified.
// Observations — matches ObservationResource / ObservationSummaryResource
// exactly. `raw_ases_json` mirrors the shape an Agent actually POSTs to
// /v1/observations (context/events/metadata) — the list endpoints never
// expose it or a verdict; only the single-resource GET does, and only
// alongside the embedded `prediction` (see toObservationSummary/Detail below).
let observations = [
  {
    id: "obs_001", agent_id: "agt_789", organization_id: "org_456",
    analysis_status: "COMPLETED",
    received_at: "2026-07-24T14:09:35Z", processing_started_at: "2026-07-24T14:09:36Z", processed_at: "2026-07-24T14:09:40Z",
    created_at: "2026-07-24T14:09:35Z", updated_at: "2026-07-24T14:09:40Z",
    raw_ases_json: {
      context: { framework: "CrewAI", execution_start_time: "2026-07-24T14:09:30Z", execution_finish_time: "2026-07-24T14:09:34Z" },
      events: [
        { header: { event_type: "api_call", timestamp: "2026-07-24T14:09:31Z" }, payload: { resource: "OpenAI API", operation: "POST", result: "success" } },
        { header: { event_type: "file_access", timestamp: "2026-07-24T14:09:33Z" }, payload: { resource: "invoices_june.xlsx", operation: "read", result: "success" } },
      ],
      metadata: { spec_version: "1.0", sdk_version: "0.4.2" },
    },
  },
  {
    id: "obs_002", agent_id: "agt_790", organization_id: "org_456",
    analysis_status: "COMPLETED",
    received_at: "2026-07-25T09:49:52Z", processing_started_at: "2026-07-25T09:49:53Z", processed_at: "2026-07-25T09:50:02Z",
    created_at: "2026-07-25T09:49:52Z", updated_at: "2026-07-25T09:50:02Z",
    raw_ases_json: {
      context: { framework: "LangChain", execution_start_time: "2026-07-25T09:49:52Z", execution_finish_time: "2026-07-25T09:50:00Z" },
      events: [
        { header: { event_type: "api_call", timestamp: "2026-07-25T09:49:53Z" }, payload: { resource: "OpenAI API", operation: "POST", result: "success" } },
        { header: { event_type: "file_access", timestamp: "2026-07-25T09:49:55Z" }, payload: { resource: "/etc/shadow", operation: "read", result: "success" } },
        { header: { event_type: "network_connection", timestamp: "2026-07-25T09:49:58Z" }, payload: { resource: "185.220.101.4", operation: "connect", result: "success" } },
      ],
      metadata: { spec_version: "1.0", sdk_version: "0.4.2" },
    },
  },
  // Still PENDING — exercises the polling behavior in ObservationDetails.jsx
  // under MOCK_MODE too, not just against a real Backend.
  {
    id: "obs_003", agent_id: "agt_791", organization_id: "org_456",
    analysis_status: "PENDING",
    received_at: "2026-07-25T10:05:00Z", processing_started_at: null, processed_at: null,
    created_at: "2026-07-25T10:05:00Z", updated_at: "2026-07-25T10:05:00Z",
    raw_ases_json: {
      context: { framework: "CrewAI", execution_start_time: "2026-07-25T10:04:55Z", execution_finish_time: "2026-07-25T10:04:59Z" },
      events: [
        { header: { event_type: "api_call", timestamp: "2026-07-25T10:04:56Z" }, payload: { resource: "OpenAI API", operation: "POST", result: "success" } },
      ],
      metadata: { spec_version: "1.0", sdk_version: "0.4.2" },
    },
  },
];

// Predictions — the Analysis module's resource, embedded into
// AlertDetailResource as `prediction` (PredictionDetailResource shape:
// id, verdict, confidence, risk_score, summary, model_version, analyzed_at,
// reasons, evidence). Alerts reference a Prediction by id, never inline it.
let predictions = [
  {
    id: "pred_555", observation_id: "obs_002",
    verdict: "MALICIOUS", confidence: 0.98, risk_score: 92,
    summary: "Prompt injection attempt combined with an out-of-scope sensitive file read and an unexpected outbound network connection.",
    model_version: "sentinelx-v3.2", analyzed_at: "2026-07-25T09:50:02Z",
    reasons: [
      "Prompt injection attempt detected in LLM API call",
      "Sensitive file accessed outside expected scope",
      "Unexpected outbound network connection",
    ],
    evidence: [
      { sequence: 1, evidence_type: "Prompt Injection", reference: "HF-PROMPT-INJECTION", confidence: 0.946 },
      { sequence: 2, evidence_type: "Threat Match", reference: "AML.T0054", confidence: null },
    ],
  },
  {
    id: "pred_556", observation_id: "obs_001",
    verdict: "SUSPICIOUS", confidence: 0.72, risk_score: 54,
    summary: "Tool usage pattern deviated from this agent's established baseline.",
    model_version: "sentinelx-v3.2", analyzed_at: "2026-07-24T14:09:40Z",
    reasons: ["Unusual tool usage detected"],
    evidence: [{ sequence: 1, evidence_type: "Threat Match", reference: "AML.T0031", confidence: 0.61 }],
  },
];

// Alerts — matches AlertSummaryResource (list) / AlertDetailResource
// (single) exactly. No agent_name/risk_score/confidence live on the Alert
// itself; those come from the related Prediction, embedded only on the
// detail endpoint.
let alerts = [
  {
    id: "alt_555", prediction_id: "pred_555", severity: "CRITICAL", status: "OPEN",
    acknowledged_at: null, acknowledged_by: null, resolved_at: null, resolved_by: null,
    created_at: "2026-07-25T09:50:05Z", updated_at: "2026-07-25T09:50:05Z",
  },
  {
    id: "alt_556", prediction_id: "pred_556", severity: "MEDIUM", status: "ACKNOWLEDGED",
    acknowledged_at: "2026-07-24T15:00:00Z", acknowledged_by: "usr_123", resolved_at: null, resolved_by: null,
    created_at: "2026-07-24T14:10:00Z", updated_at: "2026-07-24T15:00:00Z",
  },
];

// Organization mock backing store — GET/PATCH /v1/organization only expose
// id/name/slug/status/created_at/updated_at (see lib/api/organization.js);
// no plan/usage-limit fields exist on the real OrganizationResource.
export const companyInfo = {
  id: "org_456", name: "FutureBank",
};

// --- Audit / Security Logs -------------------------------------------
//
// Matches AuditLogResource exactly: id, actor_type, actor_id, action,
// resource_type, resource_id, metadata, created_at. `actor_id` is null for
// SYSTEM-actor entries, never fabricated. Write-once from the API's point
// of view — no update/delete accessor exists here, matching AuditRepository
// deliberately not having one either.

const SECURITY_ACTIONS = [
  "user.registered", "user.logged_in", "user.logged_out", "user.password_changed",
  "api_key.generated", "api_key.rotated", "api_key.revoked",
];

let auditLogs = [
  { id: "aud_010", actor_type: "USER", actor_id: "usr_123", action: "alert.acknowledged", resource_type: "Alert", resource_id: "alt_556", metadata: {}, created_at: "2026-07-24T15:00:00Z" },
  { id: "aud_009", actor_type: "SYSTEM", actor_id: null, action: "alert.created", resource_type: "Alert", resource_id: "alt_555", metadata: { severity: "CRITICAL" }, created_at: "2026-07-25T09:50:05Z" },
  { id: "aud_008", actor_type: "USER", actor_id: "usr_123", action: "api_key.rotated", resource_type: "ApiKey", resource_id: "key_002", metadata: { agent_id: "agt_790" }, created_at: "2026-07-20T11:05:00Z" },
  { id: "aud_007", actor_type: "USER", actor_id: "usr_123", action: "agent.archived", resource_type: "Agent", resource_id: "agt_792", metadata: {}, created_at: "2026-06-01T00:00:00Z" },
  { id: "aud_006", actor_type: "USER", actor_id: "usr_456", action: "agent.updated", resource_type: "Agent", resource_id: "agt_791", metadata: {}, created_at: "2026-06-15T10:00:00Z" },
  { id: "aud_005", actor_type: "USER", actor_id: "usr_123", action: "agent.created", resource_type: "Agent", resource_id: "agt_791", metadata: { agent_name: "Sales Agent" }, created_at: "2026-06-10T09:00:00Z" },
  { id: "aud_004", actor_type: "USER", actor_id: "usr_123", action: "api_key.generated", resource_type: "ApiKey", resource_id: "key_001", metadata: { agent_id: "agt_789" }, created_at: "2026-06-01T09:05:00Z" },
  { id: "aud_003", actor_type: "USER", actor_id: "usr_123", action: "user.logged_in", resource_type: "User", resource_id: "usr_123", metadata: {}, created_at: "2026-06-01T09:01:00Z" },
  { id: "aud_002", actor_type: "USER", actor_id: "usr_123", action: "user.registered", resource_type: "User", resource_id: "usr_123", metadata: {}, created_at: "2026-06-01T09:00:00Z" },
  { id: "aud_001", actor_type: "SYSTEM", actor_id: null, action: "organization.created", resource_type: "Organization", resource_id: "org_456", metadata: {}, created_at: "2026-06-01T09:00:00Z" },
];

// --- Organization & Identity Lifecycle (Team) --------------------------
//
// NOT part of the provided endpoint contract for this pass (no
// AUTHENTICATION_API/TEAM_API doc confirmed invitations or member
// management routes) — left exactly as in the prior integration attempt,
// unverified against a real Backend. Role/status values here are
// deliberately kept lowercase as before, distinct from the confirmed
// uppercase User.role enum ("OWNER"/"ADMIN"/"MEMBER") used everywhere else
// now that GET /auth/me is integrated — this mismatch is a known gap, see
// the integration report.

let members = [
  { id: "usr_123", name: "Ahmed", email: "ahmed@futurebank.com", role: "owner", status: "active" },
  { id: "usr_456", name: "Sara", email: "sara@futurebank.com", role: "admin", status: "active" },
];

let invitations = [
  {
    id: "inv_001",
    email: "mohamed@futurebank.com",
    role: "security_analyst",
    status: "pending", // pending | accepted | expired | cancelled
    invited_at: "2026-07-24T10:00:00Z",
    expires_at: "2026-07-31T10:00:00Z",
  },
];

const INVITATION_TTL_DAYS = 7;

function addDays(isoDate, days) {
  const d = new Date(isoDate);
  d.setDate(d.getDate() + days);
  return d.toISOString();
}

function ownerCount() {
  return members.filter((m) => m.role === "owner" && m.status === "active").length;
}

// --- shared helpers -------------------------------------------------------

export function paginate(list, { page = 1, per_page = 20 } = {}) {
  const total_items = list.length;
  const total_pages = Math.max(1, Math.ceil(total_items / per_page));
  const start = (page - 1) * per_page;
  const data = list.slice(start, start + per_page);
  return { data, pagination: { page: Number(page), per_page: Number(per_page), total_items, total_pages } };
}

function sortBy(list, sort) {
  if (!sort) return list;
  const desc = sort.startsWith("-");
  const key = desc ? sort.slice(1) : sort;
  return [...list].sort((a, b) => {
    if (a[key] === b[key]) return 0;
    const result = a[key] > b[key] ? 1 : -1;
    return desc ? -result : result;
  });
}

function findPrediction(predictionId) {
  return predictions.find((p) => p.id === predictionId) || null;
}

function findPredictionByObservation(observationId) {
  return predictions.find((p) => p.observation_id === observationId) || null;
}

function findObservation(observationId) {
  return observations.find((o) => o.id === observationId) || null;
}

function toObservationSummary(o) {
  return { id: o.id, agent_id: o.agent_id, analysis_status: o.analysis_status, received_at: o.received_at, created_at: o.created_at };
}

// ObservationResource's embedded `prediction` uses PredictionSummaryResource
// — deliberately excludes `evidence` (only Alert's embed includes it) and
// stays null for every analysis_status other than COMPLETED.
function toObservationDetail(o) {
  const prediction = o.analysis_status === "COMPLETED" ? findPredictionByObservation(o.id) : null;
  return {
    id: o.id,
    agent_id: o.agent_id,
    organization_id: o.organization_id,
    analysis_status: o.analysis_status,
    raw_ases_json: o.raw_ases_json,
    received_at: o.received_at,
    processing_started_at: o.processing_started_at,
    processed_at: o.processed_at,
    created_at: o.created_at,
    updated_at: o.updated_at,
    prediction: prediction
      ? {
          id: prediction.id,
          verdict: prediction.verdict,
          confidence: prediction.confidence,
          risk_score: prediction.risk_score,
          summary: prediction.summary,
          model_version: prediction.model_version,
          analyzed_at: prediction.analyzed_at,
          reasons: prediction.reasons,
        }
      : null,
  };
}

function toAlertSummary(alert) {
  const prediction = findPrediction(alert.prediction_id);
  return {
    id: alert.id,
    prediction_id: alert.prediction_id,
    severity: alert.severity,
    status: alert.status,
    created_at: alert.created_at,
    reasons: prediction?.reasons || [],
  };
}

function toAlertDetail(alert) {
  const prediction = findPrediction(alert.prediction_id);
  const observation = prediction ? findObservation(prediction.observation_id) : null;
  return {
    id: alert.id,
    severity: alert.severity,
    status: alert.status,
    acknowledged_at: alert.acknowledged_at,
    acknowledged_by: alert.acknowledged_by,
    resolved_at: alert.resolved_at,
    resolved_by: alert.resolved_by,
    created_at: alert.created_at,
    updated_at: alert.updated_at,
    prediction: prediction
      ? {
          id: prediction.id,
          verdict: prediction.verdict,
          confidence: prediction.confidence,
          risk_score: prediction.risk_score,
          summary: prediction.summary,
          model_version: prediction.model_version,
          analyzed_at: prediction.analyzed_at,
          reasons: prediction.reasons,
          evidence: prediction.evidence,
        }
      : null,
    observation: observation
      ? { id: observation.id, agent_id: observation.agent_id, received_at: observation.created_at }
      : null,
  };
}

// --- accessors used by src/lib/api/*.js mock implementations -------------

export const db = {
  // Agents — GET /v1/agents supports only `status` filtering + pagination
  // per the contract (no `search`/`framework` query params, no `sort`; the
  // Backend always orders by created_at ascending).
  getAgents: (filters = {}) => {
    let list = [...agents];
    if (filters.status) list = list.filter((a) => a.status === String(filters.status).toUpperCase());
    list = sortBy(list, "created_at");
    return paginate(list, filters);
  },
  getAgent: (id) => agents.find((a) => a.id === id) || null,
  createAgent: (payload) => {
    const now = new Date().toISOString();
    const agent = {
      id: `agt_${Math.random().toString(36).slice(2, 8)}`,
      name: payload.name,
      framework: payload.framework,
      framework_version: payload.framework_version ?? null,
      description: payload.description ?? null,
      status: "ACTIVE",
      last_seen_at: null,
      created_at: now,
      updated_at: now,
    };
    agents = [agent, ...agents];
    return agent;
  },
  updateAgent: (id, payload) => {
    const { name, framework, framework_version, description } = payload;
    const patch = { updated_at: new Date().toISOString() };
    if (name !== undefined) patch.name = name;
    if (framework !== undefined) patch.framework = framework;
    if (framework_version !== undefined) patch.framework_version = framework_version;
    if (description !== undefined) patch.description = description;
    agents = agents.map((a) => (a.id === id ? { ...a, ...patch } : a));
    return db.getAgent(id);
  },
  // One-way — Agent archival has no reverse transition on the real Backend
  // (AgentPolicy), so the mock does not offer one either. Response is the
  // partial shape the real endpoint returns: { id, status, updated_at }.
  archiveAgent: (id) => {
    const now = new Date().toISOString();
    agents = agents.map((a) => (a.id === id ? { ...a, status: "ARCHIVED", updated_at: now } : a));
    const agent = db.getAgent(id);
    return { id: agent.id, status: agent.status, updated_at: agent.updated_at };
  },
  // Response shape matches the real rotate-api-key endpoint:
  // { key_prefix, raw_key, status, created_at }. The Agent resource itself
  // never carries a key field, so nothing is written back onto `agents`.
  rotateApiKey: (id) => {
    const secret = Math.random().toString(36).slice(2, 14);
    return {
      key_prefix: `sk_live_${secret.slice(0, 4)}`,
      raw_key: `sk_live_${secret}`,
      status: "ACTIVE",
      created_at: new Date().toISOString(),
    };
  },

  // Observations — GET /v1/observations supports agent_id/analysis_status
  // filtering + pagination, ordered by received_at descending.
  getObservations: (filters = {}) => {
    let list = [...observations];
    if (filters.agent_id) list = list.filter((o) => o.agent_id === filters.agent_id);
    if (filters.analysis_status) list = list.filter((o) => o.analysis_status === String(filters.analysis_status).toUpperCase());
    list = sortBy(list, "-received_at");
    const { data, pagination } = paginate(list, filters);
    return { data: data.map(toObservationSummary), pagination };
  },
  getObservation: (id) => {
    const obs = observations.find((o) => o.id === id);
    return obs ? toObservationDetail(obs) : null;
  },

  // Alerts — GET /v1/alerts supports `status`/`severity` filtering + pagination.
  getAlerts: (filters = {}) => {
    let list = [...alerts];
    if (filters.status) list = list.filter((a) => a.status === String(filters.status).toUpperCase());
    if (filters.severity) list = list.filter((a) => a.severity === String(filters.severity).toUpperCase());
    list = sortBy(list, "-created_at");
    const { data, pagination } = paginate(list, filters);
    return { data: data.map(toAlertSummary), pagination };
  },
  getAlert: (id) => {
    const alert = alerts.find((a) => a.id === id);
    return alert ? toAlertDetail(alert) : null;
  },
  acknowledgeAlert: (id, userId = "usr_123") => {
    const alert = alerts.find((a) => a.id === id);
    if (!alert) throw new Error("Alert not found.");
    if (alert.status !== "OPEN") throw new Error("This Alert has already been acknowledged or resolved.");
    const now = new Date().toISOString();
    alerts = alerts.map((a) =>
      a.id === id ? { ...a, status: "ACKNOWLEDGED", acknowledged_at: now, acknowledged_by: userId, updated_at: now } : a
    );
    const updated = alerts.find((a) => a.id === id);
    return { id: updated.id, status: updated.status, acknowledged_at: updated.acknowledged_at, acknowledged_by: updated.acknowledged_by };
  },
  resolveAlert: (id, userId = "usr_123") => {
    const alert = alerts.find((a) => a.id === id);
    if (!alert) throw new Error("Alert not found.");
    if (alert.status === "RESOLVED") throw new Error("This Alert has already been resolved.");
    const now = new Date().toISOString();
    alerts = alerts.map((a) =>
      a.id === id ? { ...a, status: "RESOLVED", resolved_at: now, resolved_by: userId, updated_at: now } : a
    );
    const updated = alerts.find((a) => a.id === id);
    return { id: updated.id, status: updated.status, resolved_at: updated.resolved_at, resolved_by: updated.resolved_by };
  },

  // Dashboard (aggregate) — matches DashboardResource exactly:
  // organization_stats { total_agents, active_agents,
  // total_observations_last_30_days, open_alerts }, active_agents[],
  // recent_observations[], recent_alerts[], risk_summary (by Prediction
  // verdict, not Alert severity — per ADR-002, covers every analyzed
  // Observation, SAFE included).
  getDashboard: () => {
    const RECENT_ITEMS_LIMIT = 5;
    const openAlerts = alerts.filter((a) => a.status === "OPEN").length;
    const activeAgentsList = agents.filter((a) => a.status === "ACTIVE");

    const recentActiveAgents = [...activeAgentsList]
      .sort((a, b) => (a.created_at > b.created_at ? -1 : 1))
      .slice(0, RECENT_ITEMS_LIMIT)
      .map((a) => ({ id: a.id, name: a.name, status: a.status, last_seen_at: a.last_seen_at }));

    const recentObservations = [...observations]
      .sort((a, b) => (a.received_at > b.received_at ? -1 : 1))
      .slice(0, RECENT_ITEMS_LIMIT)
      .map((o) => ({ id: o.id, agent_id: o.agent_id, analysis_status: o.analysis_status, received_at: o.received_at }));

    const recentAlerts = [...alerts]
      .sort((a, b) => (a.created_at > b.created_at ? -1 : 1))
      .slice(0, RECENT_ITEMS_LIMIT)
      .map(toAlertSummary);

    const riskSummary = { SAFE: 0, SUSPICIOUS: 0, MALICIOUS: 0 };
    observations
      .filter((o) => o.analysis_status === "COMPLETED")
      .forEach((o) => {
        const prediction = findPredictionByObservation(o.id);
        if (prediction && riskSummary[prediction.verdict] !== undefined) riskSummary[prediction.verdict] += 1;
      });

    const thirtyDaysAgo = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString();

    return {
      organization_stats: {
        total_agents: agents.length,
        active_agents: activeAgentsList.length,
        total_observations_last_30_days: observations.filter((o) => o.received_at >= thirtyDaysAgo).length,
        open_alerts: openAlerts,
      },
      active_agents: recentActiveAgents,
      recent_observations: recentObservations,
      recent_alerts: recentAlerts,
      risk_summary: riskSummary,
    };
  },

  // --- Audit / Security Logs -------------------------------------------

  getAuditLogs: (filters = {}) => {
    let list = [...auditLogs];
    if (filters.actor_id) list = list.filter((e) => e.actor_id === filters.actor_id);
    if (filters.action) list = list.filter((e) => e.action === filters.action);
    if (filters.resource_type) list = list.filter((e) => e.resource_type === filters.resource_type);
    if (filters.from) list = list.filter((e) => e.created_at >= filters.from);
    if (filters.to) list = list.filter((e) => e.created_at <= filters.to);
    list = sortBy(list, "-created_at");
    return paginate(list, filters);
  },
  getAuditLog: (id) => auditLogs.find((e) => e.id === id) || null,
  getSecurityLogs: (filters = {}) => {
    let actionIn = SECURITY_ACTIONS;
    if (filters.action) actionIn = SECURITY_ACTIONS.includes(filters.action) ? [filters.action] : [];
    let list = auditLogs.filter((e) => actionIn.includes(e.action));
    if (filters.actor_id) list = list.filter((e) => e.actor_id === filters.actor_id);
    if (filters.resource_type) list = list.filter((e) => e.resource_type === filters.resource_type);
    if (filters.from) list = list.filter((e) => e.created_at >= filters.from);
    if (filters.to) list = list.filter((e) => e.created_at <= filters.to);
    list = sortBy(list, "-created_at");
    return paginate(list, filters);
  },

  // --- Organization & Identity Lifecycle (Team) — see file header note --

  getMembers: () => members.filter((m) => m.status !== "removed"),

  getInvitations: () => invitations,

  inviteMember: (email, role) => {
    const now = new Date().toISOString();
    const existingActiveMember = members.find((m) => m.email.toLowerCase() === email.toLowerCase() && m.status === "active");
    if (existingActiveMember) {
      throw new Error("This person is already a member of the organization");
    }
    const existingPendingInvite = invitations.find(
      (i) => i.email.toLowerCase() === email.toLowerCase() && i.status === "pending"
    );
    if (existingPendingInvite) {
      throw new Error("This person already has a pending invitation");
    }
    const invitation = {
      id: `inv_${Math.random().toString(36).slice(2, 8)}`,
      email,
      role,
      status: "pending",
      invited_at: now,
      expires_at: addDays(now, INVITATION_TTL_DAYS),
    };
    invitations = [...invitations, invitation];
    return invitation;
  },

  resendInvitation: (id) => {
    const now = new Date().toISOString();
    invitations = invitations.map((i) =>
      i.id === id && i.status === "pending" ? { ...i, invited_at: now, expires_at: addDays(now, INVITATION_TTL_DAYS) } : i
    );
    return invitations.find((i) => i.id === id) || null;
  },

  cancelInvitation: (id) => {
    invitations = invitations.map((i) => (i.id === id ? { ...i, status: "cancelled" } : i));
    return invitations.find((i) => i.id === id) || null;
  },

  acceptInvitation: (id, name) => {
    const invitation = invitations.find((i) => i.id === id);
    if (!invitation || invitation.status !== "pending") {
      throw new Error("This invitation is no longer valid");
    }
    const member = {
      id: `usr_${Math.random().toString(36).slice(2, 8)}`,
      name,
      email: invitation.email,
      role: invitation.role,
      status: "active",
    };
    members = [...members, member];
    invitations = invitations.map((i) => (i.id === id ? { ...i, status: "accepted" } : i));
    return member;
  },

  removeMember: (id) => {
    const member = members.find((m) => m.id === id);
    if (!member) return;
    if (member.role === "owner" && ownerCount() <= 1) {
      throw new Error("An organization must always have at least one Owner — transfer ownership first");
    }
    members = members.map((m) => (m.id === id ? { ...m, status: "removed" } : m));
  },

  updateMemberRole: (id, role) => {
    const member = members.find((m) => m.id === id);
    if (!member) throw new Error("Member not found");
    if (member.role === "owner" && role !== "owner" && ownerCount() <= 1) {
      throw new Error("Cannot demote the last remaining Owner");
    }
    members = members.map((m) => (m.id === id ? { ...m, role } : m));
    return members.find((m) => m.id === id);
  },
};
