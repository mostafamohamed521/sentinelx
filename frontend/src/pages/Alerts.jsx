import React, { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import Topbar from "../components/ui/Topbar.jsx";
import GlassCard from "../components/ui/GlassCard.jsx";
import Badge from "../components/ui/Badge.jsx";
import Reveal from "../components/ui/Reveal.jsx";
import { PageLoader, PageError, EmptyState } from "../components/ui/PageState.jsx";
import { listAlerts } from "../lib/api/alerts.js";
import { AlertTriangle } from "lucide-react";

const STATUS_OPTIONS = [
  { value: "", label: "All statuses" },
  { value: "OPEN", label: "Open" },
  { value: "ACKNOWLEDGED", label: "Acknowledged" },
  { value: "RESOLVED", label: "Resolved" },
];

const SEVERITY_OPTIONS = [
  { value: "", label: "All severities" },
  { value: "LOW", label: "Low" },
  { value: "MEDIUM", label: "Medium" },
  { value: "HIGH", label: "High" },
  { value: "CRITICAL", label: "Critical" },
];

export default function Alerts() {
  const [alerts, setAlerts] = useState(null);
  const [error, setError] = useState(null);
  const [status, setStatus] = useState("");
  const [severity, setSeverity] = useState("");

  async function load(nextStatus = status, nextSeverity = severity) {
    setError(null);
    setAlerts(null);
    try {
      const params = {};
      if (nextStatus) params.status = nextStatus;
      if (nextSeverity) params.severity = nextSeverity;
      // No `sort` param — GET /v1/alerts always orders by created_at
      // descending server-side, and the query params only ever cover
      // status/severity/page/per_page.
      const res = await listAlerts(params);
      setAlerts(res.data);
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
      <Topbar icon={AlertTriangle} title="Alerts" subtitle="Behavior that needs a human decision." />

      <div className="mb-5 flex items-center gap-3">
        <select
          value={status}
          onChange={(e) => {
            setStatus(e.target.value);
            load(e.target.value, severity);
          }}
          className="rounded-lg border border-white/[0.1] bg-[#0b0d17] px-3.5 py-2 text-xs text-white focus:border-indigo-400/50 focus:outline-none"
        >
          {STATUS_OPTIONS.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
        </select>
        <select
          value={severity}
          onChange={(e) => {
            setSeverity(e.target.value);
            load(status, e.target.value);
          }}
          className="rounded-lg border border-white/[0.1] bg-[#0b0d17] px-3.5 py-2 text-xs text-white focus:border-indigo-400/50 focus:outline-none"
        >
          {SEVERITY_OPTIONS.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
        </select>
      </div>

      {error && <PageError message={error} onRetry={() => load()} />}
      {!error && !alerts && <PageLoader />}
      {!error && alerts && alerts.length === 0 && <EmptyState icon={AlertTriangle} message="No alerts — everything looks calm." />}

      {!error && alerts && alerts.length > 0 && (
        <Reveal>
          <GlassCard className="overflow-hidden">
            <div className="flex items-center justify-between border-b border-white/[0.08] px-6 py-4">
              <span className="flex items-center gap-2 text-sm font-medium text-white">
                <AlertTriangle className="h-4 w-4 text-indigo-400" /> All Alerts
              </span>
              <span className="font-mono text-xs text-white/45">{alerts.length} total</span>
            </div>
            <table className="w-full text-left text-sm">
              <thead>
                <tr className="border-b border-white/[0.06] text-xs text-white/50">
                  <th className="px-5 py-3.5 font-medium">Alert</th>
                  <th className="px-5 py-3.5 font-medium">Severity</th>
                  <th className="px-5 py-3.5 font-medium">Status</th>
                  <th className="px-5 py-3.5 font-medium">Created</th>
                </tr>
              </thead>
              <tbody>
                {/* AlertSummaryResource is deliberately minimal:
                    { id, prediction_id, severity, status, created_at, reasons }
                    — no agent_name or risk_score at the list level (those
                    only appear on the nested `prediction` in the detail
                    view), so the primary label here is the first reason
                    rather than an agent name. */}
                {alerts.map((a) => (
                  <tr key={a.id} className="border-b border-white/[0.04] transition hover:bg-white/[0.05]">
                    <td className="px-5 py-4">
                      <Link to={`/alerts/${a.id}`} className="flex items-center gap-2.5 font-medium text-white">
                        <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-rose-500/10">
                          <AlertTriangle className="h-4 w-4 text-rose-400" />
                        </span>
                        <span className="line-clamp-1">{a.reasons?.[0] || "Alert"}</span>
                      </Link>
                    </td>
                    <td className="px-5 py-4"><Badge tone={a.severity?.toLowerCase()}>{a.severity?.toLowerCase()}</Badge></td>
                    <td className="px-5 py-4"><Badge tone={a.status?.toLowerCase()}>{a.status?.toLowerCase()}</Badge></td>
                    <td className="px-5 py-4 font-mono text-xs text-white/50">{new Date(a.created_at).toLocaleString()}</td>
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
