import React, { useEffect, useState } from "react";
import { useParams } from "react-router-dom";
import Topbar from "../components/ui/Topbar.jsx";
import GlassCard from "../components/ui/GlassCard.jsx";
import Badge from "../components/ui/Badge.jsx";
import Reveal from "../components/ui/Reveal.jsx";
import { PageLoader, PageError } from "../components/ui/PageState.jsx";
import { getObservation } from "../lib/api/observations.js";
import { getAgent } from "../lib/api/agents.js";
import { Activity, Clock, ShieldAlert } from "lucide-react";

// Analysis is explicitly asynchronous on the Backend — an Observation can
// sit at PENDING/PROCESSING for a real amount of time after this page's
// initial load. Poll while unresolved, stop once a terminal status
// (COMPLETED or FAILED) is reached.
const POLL_INTERVAL_MS = 5000;
const UNRESOLVED_STATUSES = ["PENDING", "PROCESSING"];

function eventLabel(payload) {
  if (!payload || typeof payload !== "object") return "";
  // `payload` is validated only as "an object" by the Backend — its
  // contents are not part of the documented contract. These common-looking
  // keys are shown when present as a convenience; anything else falls back
  // to a compact JSON preview so no event silently renders blank.
  const parts = [];
  if (payload.resource) parts.push(String(payload.resource));
  if (payload.operation) parts.push(String(payload.operation));
  if (payload.result) parts.push(String(payload.result));
  if (parts.length) return parts.join(" · ");
  try {
    const json = JSON.stringify(payload);
    return json.length > 80 ? `${json.slice(0, 80)}…` : json;
  } catch {
    return "";
  }
}

export default function ObservationDetails() {
  const { observationId } = useParams();
  const [obs, setObs] = useState(null);
  const [agentName, setAgentName] = useState(null);
  const [error, setError] = useState(null);

  // Refetch only — does not reset obs/error to null first, unlike the
  // initial load(), so a background poll never flashes the page back to a
  // loading state.
  async function refetch() {
    try {
      const res = await getObservation(observationId);
      setObs(res);
      if (res.agent_id) {
        // Observation resources carry only agent_id, never a name — a
        // best-effort follow-up call resolves it for the header.
        getAgent(res.agent_id).then((a) => setAgentName(a.name)).catch(() => setAgentName(null));
      }
    } catch (e) {
      setError(e.message);
    }
  }

  async function load() {
    setError(null);
    setObs(null);
    await refetch();
  }

  useEffect(() => {
    load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [observationId]);

  useEffect(() => {
    if (!obs || !UNRESOLVED_STATUSES.includes(obs.analysis_status)) return;
    const intervalId = setInterval(refetch, POLL_INTERVAL_MS);
    return () => clearInterval(intervalId);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [obs?.analysis_status, observationId]);

  if (error) return <PageError message={error} onRetry={load} />;
  if (!obs) return <PageLoader />;

  const context = obs.raw_ases_json?.context;
  const events = obs.raw_ases_json?.events || [];
  const prediction = obs.prediction;

  return (
    <div>
      <Topbar
        icon={Activity}
        title={`Observation${agentName ? ` · ${agentName}` : ""}`}
        subtitle={obs.id}
      />

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <Reveal className="lg:col-span-2">
          <GlassCard className="p-6">
            <div className="mb-5 flex items-center gap-2 text-sm font-medium text-white">
              <Clock className="h-4 w-4 text-indigo-400" /> Event Timeline
            </div>
            <div className="space-y-2">
              {events.length === 0 && <p className="text-sm text-white/45">No events recorded.</p>}
              {events.map((e, i) => (
                <div key={i} className="flex items-center gap-3 rounded-lg border border-white/[0.1] bg-white/[0.04] px-3.5 py-3 font-mono text-[12px]">
                  <span className="text-white/45">{e.header?.timestamp ? new Date(e.header.timestamp).toLocaleTimeString() : "—"}</span>
                  <span className="w-40 shrink-0 text-white/70">{e.header?.event_type}</span>
                  <span className="truncate text-white/55">{eventLabel(e.payload)}</span>
                </div>
              ))}
            </div>
          </GlassCard>
        </Reveal>

        <Reveal delay={100}>
          <GlassCard className="p-6">
            <div className="mb-5 flex items-center gap-2 text-sm font-medium text-white">
              <ShieldAlert className="h-4 w-4 text-indigo-400" /> Prediction
            </div>
            <div className="space-y-3 text-sm">
              <div className="flex items-center justify-between">
                <span className="text-white/55">Status</span>
                <Badge
                  tone={
                    obs.analysis_status === "COMPLETED" ? "active"
                      : obs.analysis_status === "FAILED" ? "high"
                      : "acknowledged"
                  }
                >
                  {obs.analysis_status?.toLowerCase()}
                </Badge>
              </div>

              {prediction ? (
                <>
                  <div className="flex items-center justify-between">
                    <span className="text-white/55">Verdict</span>
                    <Badge tone={prediction.verdict === "SAFE" ? "low" : prediction.verdict === "SUSPICIOUS" ? "medium" : "high"}>{prediction.verdict}</Badge>
                  </div>
                  <div className="flex items-center justify-between">
                    <span className="text-white/55">Confidence</span>
                    <span className="text-white">{Math.round(prediction.confidence * 100)}%</span>
                  </div>
                  <div className="flex items-center justify-between">
                    <span className="text-white/55">Risk Score</span>
                    <span className="text-white">{prediction.risk_score}</span>
                  </div>
                  {prediction.summary && (
                    <p className="border-t border-white/[0.06] pt-3 text-xs leading-relaxed text-white/65">{prediction.summary}</p>
                  )}
                </>
              ) : (
                <p className="text-xs text-white/45">
                  {obs.analysis_status === "FAILED"
                    ? "Analysis failed for this observation — no prediction was produced."
                    : "Analysis hasn't completed yet."}
                </p>
              )}

              {context && (
                <div className="border-t border-white/[0.06] pt-3">
                  <div className="text-white/55">Context</div>
                  <div className="mt-2 space-y-1 text-xs text-white/65">
                    <div>Framework: {context.framework}</div>
                    {context.execution_start_time && <div>Started: {new Date(context.execution_start_time).toLocaleString()}</div>}
                    {context.execution_finish_time && <div>Finished: {new Date(context.execution_finish_time).toLocaleString()}</div>}
                  </div>
                </div>
              )}
            </div>
          </GlassCard>
        </Reveal>
      </div>
    </div>
  );
}
