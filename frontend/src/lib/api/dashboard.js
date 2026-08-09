// Dashboard API — matches the provided endpoint contract exactly:
//   GET /v1/dashboard
//
// A single aggregated response so the frontend doesn't have to make
// several requests to render the landing screen. Response shape:
//   {
//     organization_stats: { total_agents, active_agents,
//                            total_observations_last_30_days, open_alerts },
//     active_agents: [{ id, name, status, last_seen_at }],       (max 5)
//     recent_observations: [{ id, agent_id, analysis_status, received_at }], (max 5)
//     recent_alerts: [{ id, severity, status, created_at, reasons }],       (max 5)
//     risk_summary: { SAFE, SUSPICIOUS, MALICIOUS }
//   }
// `risk_summary` groups by Prediction verdict (not Alert severity) and
// covers every analyzed Observation, SAFE included.

import { apiFetch, MOCK_MODE } from "../apiClient.js";
import { db } from "../mockDb.js";

function delay(ms = 400) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

export async function getDashboard() {
  if (MOCK_MODE) {
    await delay();
    return db.getDashboard();
  }
  const res = await apiFetch("/dashboard");
  return res.data;
}
