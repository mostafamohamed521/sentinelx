import React, { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import Topbar from "../components/ui/Topbar.jsx";
import GlassCard from "../components/ui/GlassCard.jsx";
import Badge from "../components/ui/Badge.jsx";
import Reveal from "../components/ui/Reveal.jsx";
import { PageLoader, PageError, EmptyState } from "../components/ui/PageState.jsx";
import { listObservations } from "../lib/api/observations.js";
import { listAgents } from "../lib/api/agents.js";
import { Activity } from "lucide-react";

const STATUS_OPTIONS = [
  { value: "", label: "All statuses" },
  { value: "PENDING", label: "Pending" },
  { value: "PROCESSING", label: "Processing" },
  { value: "COMPLETED", label: "Completed" },
  { value: "FAILED", label: "Failed" },
];

const STATUS_TONE = { COMPLETED: "active", FAILED: "high", PENDING: "acknowledged", PROCESSING: "acknowledged" };

export default function Observations() {
  const [observations, setObservations] = useState(null);
  const [agentsById, setAgentsById] = useState({});
  const [error, setError] = useState(null);
  const [status, setStatus] = useState("");
  const [agentId, setAgentId] = useState("");

  async function load(nextStatus = status, nextAgentId = agentId) {
    setError(null);
    setObservations(null);
    try {
      const params = {};
      if (nextStatus) params.analysis_status = nextStatus;
      if (nextAgentId) params.agent_id = nextAgentId;
      // No `sort` param — GET /v1/observations always orders by
      // received_at descending server-side.
      const [obsRes, agentsRes] = await Promise.all([
        listObservations(params),
        // ObservationSummaryResource has no agent_name — an agent list is
        // fetched once to resolve a friendly name/filter dropdown client-side.
        listAgents(),
      ]);
      setObservations(obsRes.data);
      setAgentsById(Object.fromEntries(agentsRes.data.map((a) => [a.id, a.name])));
    } catch (e) {
      setError(e.message);
    }
  }

  useEffect(() => {
    load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  return (
    <div>
      <Topbar icon={Activity} title="Observations" subtitle="Every batch of activity submitted by your agents." />

      <div className="mb-5 flex items-center gap-3">
        <select
          value={status}
          onChange={(e) => { setStatus(e.target.value); load(e.target.value, agentId); }}
          className="rounded-lg border border-white/[0.1] bg-[#0b0d17] px-3.5 py-2 text-xs text-white focus:border-indigo-400/50 focus:outline-none"
        >
          {STATUS_OPTIONS.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
        </select>
        <select
          value={agentId}
          onChange={(e) => { setAgentId(e.target.value); load(status, e.target.value); }}
          className="rounded-lg border border-white/[0.1] bg-[#0b0d17] px-3.5 py-2 text-xs text-white focus:border-indigo-400/50 focus:outline-none"
        >
          <option value="">All agents</option>
          {Object.entries(agentsById).map(([id, name]) => (
            <option key={id} value={id}>{name}</option>
          ))}
        </select>
      </div>

      {error && <PageError message={error} onRetry={() => load()} />}
      {!error && !observations && <PageLoader />}
      {!error && observations && observations.length === 0 && (
        <EmptyState icon={Activity} message="No observations submitted yet." />
      )}

      {!error && observations && observations.length > 0 && (
        <Reveal>
          <GlassCard className="overflow-hidden">
            <div className="flex items-center justify-between border-b border-white/[0.08] px-6 py-4">
              <span className="flex items-center gap-2 text-sm font-medium text-white">
                <Activity className="h-4 w-4 text-indigo-400" /> All Observations
              </span>
              <span className="font-mono text-xs text-white/45">{observations.length} total</span>
            </div>
            <table className="w-full text-left text-sm">
              <thead>
                <tr className="border-b border-white/[0.06] text-xs text-white/50">
                  <th className="px-5 py-3.5 font-medium">Agent</th>
                  <th className="px-5 py-3.5 font-medium">Status</th>
                  <th className="px-5 py-3.5 font-medium">Received</th>
                </tr>
              </thead>
              <tbody>
                {/* ObservationSummaryResource: { id, agent_id, analysis_status,
                    received_at, created_at } — no verdict/confidence/risk_score
                    at the list level; those only exist on the detail view,
                    nested under `prediction`, once analysis has completed. */}
                {observations.map((o) => (
                  <tr key={o.id} className="border-b border-white/[0.04] transition hover:bg-white/[0.05]">
                    <td className="px-5 py-4">
                      <Link to={`/observations/${o.id}`} className="flex items-center gap-2.5 font-medium text-white">
                        <span className="flex h-7 w-7 items-center justify-center rounded-lg bg-indigo-500/10">
                          <Activity className="h-4 w-4 text-indigo-400" />
                        </span>
                        {agentsById[o.agent_id] || o.agent_id}
                      </Link>
                    </td>
                    <td className="px-5 py-4">
                      <Badge tone={STATUS_TONE[o.analysis_status] || "neutral"}>{o.analysis_status?.toLowerCase()}</Badge>
                    </td>
                    <td className="px-5 py-4 text-white/50">{new Date(o.received_at || o.created_at).toLocaleString()}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </GlassCard>
        </Reveal>
      )}
    </div>
  );
}
