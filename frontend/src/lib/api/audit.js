// Audit / Security Logs API — matches the provided endpoint contract exactly:
//   GET /v1/audit-logs
//   GET /v1/audit-logs/{auditLogId}
//   GET /v1/security-logs
//
// Owner and Admin only server-side (Member gets 403). No prior frontend
// integration existed for this resource at all — this module, and the
// AuditLogs page that consumes it, are new.
//
// AuditLogResource is used identically for both the list item shape and
// the single-entry detail shape (id, actor_type, actor_id, action,
// resource_type, resource_id, metadata, created_at) — unlike Agent/
// Observation there is no separate "detail" shape, so no extra unwrapping
// concerns beyond the standard `{ data, pagination }` / `{ data }` envelopes.
//
// GET /v1/security-logs shares the exact same response shape and query
// parameters as /v1/audit-logs, just pre-filtered server-side to a fixed
// set of security-relevant actions.

import { apiFetch, toQueryString, MOCK_MODE, ApiError } from "../apiClient.js";
import { db } from "../mockDb.js";

function delay(ms = 350) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

// GET /v1/audit-logs — query params: actor_id, action, resource_type,
// from, to, page, per_page.
export async function listAuditLogs(params = {}) {
  if (MOCK_MODE) {
    await delay();
    return db.getAuditLogs(params);
  }
  return apiFetch(`/audit-logs${toQueryString(params)}`);
}

// GET /v1/audit-logs/{auditLogId}
export async function getAuditLog(auditLogId) {
  if (MOCK_MODE) {
    await delay();
    const entry = db.getAuditLog(auditLogId);
    if (!entry) throw new ApiError("NOT_FOUND", "Audit log entry not found.");
    return entry;
  }
  const res = await apiFetch(`/audit-logs/${auditLogId}`);
  return res.data;
}

// GET /v1/security-logs — same query params as /audit-logs; `action`
// narrows WITHIN the fixed security-action set and never expands beyond it.
export async function listSecurityLogs(params = {}) {
  if (MOCK_MODE) {
    await delay();
    return db.getSecurityLogs(params);
  }
  return apiFetch(`/security-logs${toQueryString(params)}`);
}
