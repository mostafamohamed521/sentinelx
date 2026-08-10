import React, { useEffect, useState } from "react";
import { useParams, Link } from "react-router-dom";
import Topbar from "../components/ui/Topbar.jsx";
import GlassCard from "../components/ui/GlassCard.jsx";
import Badge from "../components/ui/Badge.jsx";
import Reveal from "../components/ui/Reveal.jsx";
import Modal from "../components/ui/Modal.jsx";
import { PageLoader, PageError } from "../components/ui/PageState.jsx";
import { useAuth } from "../lib/AuthContext.jsx";
import { getAgent, updateAgent, archiveAgent, rotateApiKey, listAgentObservations } from "../lib/api/agents.js";
import { Bot, KeyRound, Archive, Copy, CheckCircle2, Pencil, AlertCircle, Loader2, Activity } from "lucide-react";

const FRAMEWORK_PRESETS = ["CrewAI", "LangChain", "AutoGen", "Custom"];

// Matches Modal's opaque panel contrast — see the identical constant in
// pages/Agents.jsx (kept per-file rather than shared to avoid a cross-page
// import for one style string).
const FIELD_CLASS =
  "w-full rounded-lg border border-white/[0.14] bg-white/[0.05] px-3.5 py-2.5 text-sm text-white placeholder:text-white/45 transition focus:border-indigo-400/60 focus:bg-white/[0.07] focus:outline-none";

function EditAgentModal({ agent, onClose, onSaved }) {
  const [name, setName] = useState(agent.name || "");
  const [framework, setFramework] = useState(agent.framework || "");
  const [frameworkVersion, setFrameworkVersion] = useState(agent.framework_version || "");
  const [description, setDescription] = useState(agent.description || "");
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState(null);

  async function handleSubmit(e) {
    e.preventDefault();
    setError(null);
    setSubmitting(true);
    try {
      // PATCH /agents/{id} accepts partial updates but requires at least
      // one field — since this form always shows every field, send them all.
      const updated = await updateAgent(agent.id, {
        name,
        framework,
        framework_version: frameworkVersion || null,
        description: description || null,
      });
      onSaved(updated);
    } catch (err) {
      // 409 CONFLICT on a name collision, 422 on validation — both surface
      // here as err.message.
      setError(err.message);
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <Modal
      icon={Pencil}
      title="Edit agent"
      subtitle={`Update ${agent.name}'s metadata.`}
      onClose={onClose}
      onSubmit={handleSubmit}
      footer={
        <>
          <button
            type="button"
            onClick={onClose}
            className="flex-1 rounded-lg border border-white/[0.12] py-2.5 text-sm font-medium text-white/70 transition hover:bg-white/[0.06]"
          >
            Cancel
          </button>
          <button
            type="submit"
            disabled={submitting}
            className="flex flex-1 items-center justify-center gap-1.5 rounded-lg bg-white py-2.5 text-sm font-semibold text-[#07080f] shadow-[0_8px_24px_-8px_rgba(255,255,255,0.25)] transition hover:bg-white/90 disabled:cursor-not-allowed disabled:opacity-60"
          >
            {submitting ? (
              <>
                <Loader2 className="h-3.5 w-3.5 animate-spin" /> Saving...
              </>
            ) : (
              "Save changes"
            )}
          </button>
        </>
      }
    >
      {error && (
        <div className="mb-5 flex items-center gap-2 rounded-lg border border-rose-500/20 bg-rose-500/[0.08] px-3.5 py-2.5 text-xs text-rose-300">
          <AlertCircle className="h-3.5 w-3.5 shrink-0" /> {error}
        </div>
      )}

      <div className="space-y-5">
        <div>
          <label className="mb-1.5 block text-xs font-medium text-white/65">Agent name</label>
          <input required value={name} onChange={(e) => setName(e.target.value)} className={FIELD_CLASS} />
        </div>

        <div>
          <label className="mb-1.5 block text-xs font-medium text-white/65">Framework</label>
          <div className="mb-2.5 flex flex-wrap gap-1.5">
            {FRAMEWORK_PRESETS.map((f) => (
              <button
                type="button"
                key={f}
                onClick={() => setFramework(f)}
                className={`rounded-full border px-3 py-1 text-[11px] font-medium transition ${
                  framework === f
                    ? "border-indigo-400/50 bg-indigo-500/15 text-indigo-300"
                    : "border-white/[0.12] text-white/65 hover:border-white/25 hover:text-white/80"
                }`}
              >
                {f}
              </button>
            ))}
          </div>
          <input required value={framework} onChange={(e) => setFramework(e.target.value)} className={FIELD_CLASS} />
        </div>

        <div>
          <label className="mb-1.5 block text-xs font-medium text-white/65">Framework version</label>
          <input
            value={frameworkVersion}
            onChange={(e) => setFrameworkVersion(e.target.value)}
            className={`max-w-[10rem] ${FIELD_CLASS}`}
          />
        </div>

        <div>
          <label className="mb-1.5 block text-xs font-medium text-white/65">Description</label>
          <textarea
            value={description}
            onChange={(e) => setDescription(e.target.value)}
            rows={3}
            className={`resize-none ${FIELD_CLASS}`}
          />
        </div>
      </div>
    </Modal>
  );
}

export default function AgentDetails() {
  const { agentId } = useParams();
  const { user } = useAuth();
  const [agent, setAgent] = useState(null);
  const [observations, setObservations] = useState([]);
  const [error, setError] = useState(null);
  const [actionError, setActionError] = useState(null);
  const [busy, setBusy] = useState(false);
  const [newKey, setNewKey] = useState(null);
  const [copied, setCopied] = useState(false);
  const [showEdit, setShowEdit] = useState(false);

  // Archive, rotate-api-key, and update are all Owner-only server-side
  // (403 for anyone else) — gated here to avoid inevitable failed requests.
  const isOwner = user?.role === "OWNER";

  async function load() {
    setError(null);
    setAgent(null);
    try {
      const [agentRes, obsRes] = await Promise.all([
        getAgent(agentId),
        listAgentObservations(agentId),
      ]);
      setAgent(agentRes);
      setObservations(obsRes.data);
    } catch (e) {
      setError(e.message);
    }
  }

  useEffect(() => {
    load();
    setNewKey(null);
    setActionError(null);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [agentId]);

  // One-way — Agent archival has no reverse transition on the real Backend
  // (AgentPolicy), so there is nothing to toggle back.
  async function handleArchive() {
    setBusy(true);
    setActionError(null);
    try {
      const updated = await archiveAgent(agentId);
      setAgent((prev) => ({ ...prev, ...updated }));
    } catch (e) {
      // e.g. 409 CONFLICT if it was already archived by someone else.
      setActionError(e.message);
    } finally {
      setBusy(false);
    }
  }

  async function handleRotateKey() {
    setBusy(true);
    setActionError(null);
    try {
      const res = await rotateApiKey(agentId);
      // raw_key is shown exactly once by the Backend and is never
      // retrievable again — it is intentionally not merged onto the Agent
      // resource itself (AgentResource never carries a key field).
      setNewKey(res.raw_key);
    } catch (e) {
      setActionError(e.message);
    } finally {
      setBusy(false);
    }
  }

  function copyKey() {
    if (!newKey) return;
    navigator.clipboard?.writeText(newKey);
    setCopied(true);
    setTimeout(() => setCopied(false), 1500);
  }

  if (error) return <PageError message={error} onRetry={load} />;
  if (!agent) return <PageLoader />;

  return (
    <div>
      <Topbar
        icon={Bot}
        title={agent.name}
        subtitle={agent.description}
        actions={
          <>
            <button
              onClick={() => setShowEdit(true)}
              disabled={!isOwner}
              title={isOwner ? undefined : "Only workspace Owners can edit agents"}
              className="flex items-center gap-1.5 rounded-lg border border-white/[0.1] px-3.5 py-2 text-xs font-medium text-white/70 transition hover:bg-white/[0.05] disabled:cursor-not-allowed disabled:opacity-40"
            >
              <Pencil className="h-3.5 w-3.5" /> Edit
            </button>
            <button
              onClick={handleRotateKey}
              disabled={busy || !isOwner || agent.status === "ARCHIVED"}
              title={isOwner ? undefined : "Only workspace Owners can rotate API keys"}
              className="flex items-center gap-1.5 rounded-lg border border-white/[0.1] px-3.5 py-2 text-xs font-medium text-white/70 transition hover:bg-white/[0.05] disabled:cursor-not-allowed disabled:opacity-40"
            >
              <KeyRound className="h-3.5 w-3.5" /> Rotate API Key
            </button>
            {/* Archival is one-way — an already-Archived Agent gets no
                action button to leave that state, matching the real
                Backend's actual, deliberate state machine. */}
            {agent.status !== "ARCHIVED" && (
              <button
                onClick={handleArchive}
                disabled={busy || !isOwner}
                title={isOwner ? undefined : "Only workspace Owners can archive agents"}
                className="flex items-center gap-1.5 rounded-lg border border-white/[0.1] px-3.5 py-2 text-xs font-medium text-white/70 transition hover:bg-white/[0.05] disabled:cursor-not-allowed disabled:opacity-40"
              >
                <Archive className="h-3.5 w-3.5" /> Archive
              </button>
            )}
          </>
        }
      />

      {actionError && (
        <div className="mb-6 flex items-center gap-2 rounded-lg border border-rose-500/20 bg-rose-500/[0.08] px-4 py-3 text-xs text-rose-300">
          <AlertCircle className="h-4 w-4 shrink-0" /> {actionError}
        </div>
      )}

      {newKey && (
        <div className="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-emerald-500/20 bg-emerald-500/[0.08] px-4 py-3 text-xs">
          <div className="flex items-center gap-2 text-emerald-300">
            <CheckCircle2 className="h-4 w-4 shrink-0" />
            New API key generated — copy it now, it won't be shown again: <code className="text-white/80">{newKey}</code>
          </div>
          <button onClick={copyKey} className="flex shrink-0 items-center gap-1 rounded-md border border-white/[0.1] px-2.5 py-1.5 text-white/60 hover:bg-white/[0.05]">
            <Copy className="h-3 w-3" /> {copied ? "Copied" : "Copy"}
          </button>
        </div>
      )}

      <div className="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
        <GlassCard className="p-4">
          <div className="text-[10px] text-white/50">Framework</div>
          <div className="mt-1 text-sm font-semibold text-white">{agent.framework}</div>
        </GlassCard>
        <GlassCard className="p-4">
          <div className="text-[10px] text-white/50">Version</div>
          <div className="mt-1 text-sm font-semibold text-white">{agent.framework_version || "—"}</div>
        </GlassCard>
        <GlassCard className="p-4">
          <div className="text-[10px] text-white/50">Status</div>
          {/* Badge's style tokens are lowercase; the Backend's real status
              values are uppercase — normalized here at the display call site. */}
          <div className="mt-1"><Badge tone={agent.status?.toLowerCase()}>{agent.status?.toLowerCase()}</Badge></div>
        </GlassCard>
        <GlassCard className="p-4">
          <div className="text-[10px] text-white/50">Last Seen</div>
          <div className="mt-1 font-mono text-sm font-semibold text-white">
            {agent.last_seen_at ? new Date(agent.last_seen_at).toLocaleString() : "Never"}
          </div>
        </GlassCard>
      </div>

      <Reveal>
        <GlassCard className="p-6">
          <div className="mb-5 flex items-center gap-2 text-sm font-medium text-white">
            <Activity className="h-4 w-4 text-indigo-400" /> Recent Observations
          </div>
          <div className="space-y-2.5">
            {observations.length === 0 && <p className="text-sm text-white/45">No observations yet.</p>}
            {observations.map((o) => (
              <Link
                key={o.id}
                to={`/observations/${o.id}`}
                className="flex items-center justify-between rounded-lg border border-white/[0.1] bg-white/[0.04] px-3.5 py-3 text-sm transition hover:bg-white/[0.05]"
              >
                <span className="font-mono text-xs text-white/60">{new Date(o.received_at || o.created_at).toLocaleString()}</span>
                {/* ObservationSummaryResource carries analysis_status only —
                    no verdict at the list level; the verdict only appears
                    once analysis has completed, on the detail endpoint. */}
                <Badge
                  tone={
                    o.analysis_status === "COMPLETED" ? "active"
                      : o.analysis_status === "FAILED" ? "high"
                      : "acknowledged"
                  }
                >
                  {o.analysis_status?.toLowerCase()}
                </Badge>
              </Link>
            ))}
          </div>
        </GlassCard>
      </Reveal>

      {showEdit && (
        <EditAgentModal
          agent={agent}
          onClose={() => setShowEdit(false)}
          onSaved={(updated) => {
            setAgent((prev) => ({ ...prev, ...updated }));
            setShowEdit(false);
          }}
        />
      )}
    </div>
  );
}
