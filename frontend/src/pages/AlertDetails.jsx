import React, { useEffect, useState } from "react";
import { useParams, Link } from "react-router-dom";
import Topbar from "../components/ui/Topbar.jsx";
import GlassCard from "../components/ui/GlassCard.jsx";
import Badge from "../components/ui/Badge.jsx";
import Reveal from "../components/ui/Reveal.jsx";
import { PageLoader, PageError } from "../components/ui/PageState.jsx";
import { getAlert, acknowledgeAlert, resolveAlert } from "../lib/api/alerts.js";
import { getAgent } from "../lib/api/agents.js";
import { AlertTriangle, CheckCircle2, ShieldAlert, ArrowRight, AlertCircle } from "lucide-react";

// An Alert's own content (severity, its related Prediction/evidence) is
// fixed once created, but its `status` can still change from another actor
// acknowledging or resolving it elsewhere — poll while unresolved, stop
// once RESOLVED (terminal).
const POLL_INTERVAL_MS = 5000;
const TERMINAL_STATUS = "RESOLVED";

export default function AlertDetails() {
  const { alertId } = useParams();
  const [alert, setAlert] = useState(null);
  const [agentName, setAgentName] = useState(null);
  const [error, setError] = useState(null);
  const [actionError, setActionError] = useState(null);
  const [tab, setTab] = useState("summary");
  const [busy, setBusy] = useState(false);

  async function refetch() {
    try {
      const res = await getAlert(alertId);
      setAlert(res);
      // The Agent name isn't part of AlertDetailResource (only
      // observation.agent_id is) — resolved with a best-effort follow-up
      // call so the header can still show a friendly title.
      if (res.observation?.agent_id) {
        getAgent(res.observation.agent_id)
          .then((agent) => setAgentName(agent.name))
          .catch(() => setAgentName(null));
      }
    } catch (e) {
      setError(e.message);
    }
  }

  async function load() {
    setError(null);
    setAlert(null);
    await refetch();
  }

  useEffect(() => {
    load();
    setTab("summary");
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [alertId]);

  useEffect(() => {
    if (!alert || alert.status === TERMINAL_STATUS) return;
    const intervalId = setInterval(refetch, POLL_INTERVAL_MS);
    return () => clearInterval(intervalId);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [alert?.status, alertId]);

  async function handleAcknowledge() {
    setBusy(true);
    setActionError(null);
    try {
      const updated = await acknowledgeAlert(alertId);
      setAlert((prev) => ({ ...prev, ...updated }));
    } catch (e) {
      // e.g. 409 CONFLICT if someone else already acknowledged/resolved it.
      setActionError(e.message);
    } finally {
      setBusy(false);
    }
  }

  async function handleResolve() {
    setBusy(true);
    setActionError(null);
    try {
      const updated = await resolveAlert(alertId);
      setAlert((prev) => ({ ...prev, ...updated }));
    } catch (e) {
      setActionError(e.message);
    } finally {
      setBusy(false);
    }
  }

  if (error) return <PageError message={error} onRetry={load} />;
  if (!alert) return <PageLoader />;

  const prediction = alert.prediction;
  const observation = alert.observation;

  return (
    <div>
      <Topbar
        icon={AlertTriangle}
        title={`Alert${agentName ? ` · ${agentName}` : ""}`}
        subtitle={alert.id}
        actions={
          <>
            <button
              onClick={handleAcknowledge}
              disabled={busy || alert.status !== "OPEN"}
              className="flex items-center gap-1.5 rounded-lg border border-white/[0.1] px-3.5 py-2 text-xs font-medium text-white/70 transition hover:bg-white/[0.05] disabled:opacity-40"
            >
              <ShieldAlert className="h-3.5 w-3.5" /> Acknowledge
            </button>
            <button
              onClick={handleResolve}
              disabled={busy || alert.status === "RESOLVED"}
              className="flex items-center gap-1.5 rounded-lg bg-white px-3.5 py-2 text-xs font-semibold text-[#07080f] transition hover:bg-white/90 disabled:opacity-40"
            >
              <CheckCircle2 className="h-3.5 w-3.5" /> Resolve
            </button>
          </>
        }
      />

      {actionError && (
        <div className="mb-6 flex items-center gap-2 rounded-lg border border-rose-500/20 bg-rose-500/[0.08] px-4 py-3 text-xs text-rose-300">
          <AlertCircle className="h-4 w-4 shrink-0" /> {actionError}
        </div>
      )}

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <Reveal className="lg:col-span-2">
          <GlassCard className="overflow-hidden">
            <div className="flex items-center justify-between border-b border-rose-500/20 bg-rose-500/[0.08] px-5 py-4">
              <div className="flex items-center gap-2 text-sm font-medium text-rose-300">
                <AlertTriangle className="h-4 w-4" /> {prediction?.summary || prediction?.reasons?.[0] || "Flagged behavior"}
              </div>
              <Badge tone={alert.severity?.toLowerCase()}>{alert.severity?.toLowerCase()}</Badge>
            </div>

            <div className="flex gap-5 border-b border-white/[0.06] px-5 pt-4 text-xs text-white/50">
              {["summary", "timeline", "evidence"].map((t) => (
                <button
                  key={t}
                  onClick={() => setTab(t)}
                  className={`pb-3 capitalize transition ${tab === t ? "border-b-2 border-indigo-400 text-white" : "hover:text-white/60"}`}
                >
                  {t}
                </button>
              ))}
            </div>

            <div className="p-5">
              {tab === "summary" && (
                <div className="space-y-4 text-sm">
                  {prediction ? (
                    <div>
                      <div className="mb-2 text-white/55">Why this was flagged</div>
                      <ul className="space-y-1.5">
                        {(prediction.reasons || []).map((r, i) => (
                          <li key={i} className="flex items-start gap-2 text-white/70">
                            <AlertTriangle className="mt-0.5 h-3.5 w-3.5 shrink-0 text-rose-400" /> {r}
                          </li>
                        ))}
                      </ul>
                    </div>
                  ) : (
                    <p className="text-white/55">
                      The Prediction backing this Alert is no longer available.
                    </p>
                  )}
                  {observation && (
                    <Link
                      to={`/observations/${observation.id}`}
                      className="inline-flex items-center gap-1.5 text-xs font-medium text-indigo-400 hover:text-indigo-300"
                    >
                      View related observation <ArrowRight className="h-3 w-3" />
                    </Link>
                  )}
                </div>
              )}

              {tab === "timeline" && (
                <div className="space-y-2.5 text-sm">
                  <div className="flex items-center justify-between rounded-lg border border-white/[0.1] bg-white/[0.04] px-3.5 py-3">
                    <span className="text-white/60">Created</span>
                    <span className="font-mono text-white/55">{new Date(alert.created_at).toLocaleString()}</span>
                  </div>
                  {prediction?.analyzed_at && (
                    <div className="flex items-center justify-between rounded-lg border border-white/[0.1] bg-white/[0.04] px-3.5 py-3">
                      <span className="text-white/60">Analyzed</span>
                      <span className="font-mono text-white/55">{new Date(prediction.analyzed_at).toLocaleString()}</span>
                    </div>
                  )}
                  {alert.acknowledged_at && (
                    <div className="flex items-center justify-between rounded-lg border border-white/[0.1] bg-white/[0.04] px-3.5 py-3">
                      <span className="text-white/60">Acknowledged</span>
                      <span className="font-mono text-white/55">{new Date(alert.acknowledged_at).toLocaleString()}</span>
                    </div>
                  )}
                  {alert.resolved_at && (
                    <div className="flex items-center justify-between rounded-lg border border-white/[0.1] bg-white/[0.04] px-3.5 py-3">
                      <span className="text-white/60">Resolved</span>
                      <span className="font-mono text-white/55">{new Date(alert.resolved_at).toLocaleString()}</span>
                    </div>
                  )}
                  <p className="pt-1 text-xs text-white/45">
                    Full event timeline is available on the related Observation page.
                  </p>
                </div>
              )}

              {tab === "evidence" && (
                <div className="space-y-2.5">
                  {(!prediction?.evidence || prediction.evidence.length === 0) && (
                    <p className="text-sm text-white/45">No evidence recorded for this alert.</p>
                  )}
                  {prediction?.evidence?.map((e) => (
                    <div key={e.sequence} className="rounded-lg border border-white/[0.06] bg-white/[0.03] p-3">
                      <div className="text-sm text-white/70">{e.evidence_type}</div>
                      <div className="mt-0.5 font-mono text-[11px] text-white/50">
                        {e.reference} {e.confidence != null && `· ${Math.round(e.confidence * 100)}%`}
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </GlassCard>
        </Reveal>

        <Reveal delay={100}>
          <GlassCard className="p-6">
            <div className="mb-5 flex items-center gap-2 text-sm font-medium text-white">
              <ShieldAlert className="h-4 w-4 text-indigo-400" /> Details
            </div>
            <div className="space-y-3 text-sm">
              <div className="flex items-center justify-between">
                <span className="text-white/55">Status</span>
                <Badge tone={alert.status?.toLowerCase()}>{alert.status?.toLowerCase()}</Badge>
              </div>
              {prediction && (
                <>
                  <div className="flex items-center justify-between">
                    <span className="text-white/55">Verdict</span>
                    <span className="font-mono text-white">{prediction.verdict}</span>
                  </div>
                  <div className="flex items-center justify-between">
                    <span className="text-white/55">Confidence</span>
                    <span className="font-mono text-white">{Math.round(prediction.confidence * 100)}%</span>
                  </div>
                  <div className="flex items-center justify-between">
                    <span className="text-white/55">Risk Score</span>
                    <span className="font-mono text-white">{prediction.risk_score}</span>
                  </div>
                  <div className="flex items-center justify-between">
                    <span className="text-white/55">Model</span>
                    <span className="text-white/60">{prediction.model_version}</span>
                  </div>
                </>
              )}
            </div>
          </GlassCard>
        </Reveal>
      </div>
    </div>
  );
}
