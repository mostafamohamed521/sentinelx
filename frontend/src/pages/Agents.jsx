import React, { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import Topbar from "../components/ui/Topbar.jsx";
import GlassCard from "../components/ui/GlassCard.jsx";
import Badge from "../components/ui/Badge.jsx";
import Reveal from "../components/ui/Reveal.jsx";
import Modal from "../components/ui/Modal.jsx";
import { PageLoader, PageError, EmptyState } from "../components/ui/PageState.jsx";
import { useAuth } from "../lib/AuthContext.jsx";
import { listAgents, createAgent } from "../lib/api/agents.js";
import { Bot, Plus, AlertCircle, Loader2 } from "lucide-react";

const STATUS_OPTIONS = [
  { value: "", label: "All statuses" },
  { value: "ACTIVE", label: "Active" },
  { value: "ARCHIVED", label: "Archived" },
];

// Not an enum on the Backend (framework is a free-text string on Agent) —
// just common values worth one tap instead of typing, per StoreAgentRequest.
const FRAMEWORK_PRESETS = ["CrewAI", "LangChain", "AutoGen", "Custom"];

// Shared input styling across the modal forms in this file — solid enough
// to read clearly against Modal's opaque panel (bg-[0.05] rather than the
// near-invisible bg-[0.03] used elsewhere against the app's normal
// background, which this darker modal context needs slightly more
// contrast than).
const FIELD_CLASS =
  "w-full rounded-lg border border-white/[0.14] bg-white/[0.05] px-3.5 py-2.5 text-sm text-white placeholder:text-white/45 transition focus:border-indigo-400/60 focus:bg-white/[0.07] focus:outline-none";

function CreateAgentModal({ onClose, onCreated }) {
  const [name, setName] = useState("");
  const [framework, setFramework] = useState("");
  const [frameworkVersion, setFrameworkVersion] = useState("");
  const [description, setDescription] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState(null);

  async function handleSubmit(e) {
    e.preventDefault();
    setError(null);
    setSubmitting(true);
    try {
      const agent = await createAgent({
        name,
        framework,
        framework_version: frameworkVersion || undefined,
        description: description || undefined,
      });
      onCreated(agent);
    } catch (err) {
      // 409 CONFLICT ("An Agent with this name already exists in your
      // organization") and 422 VALIDATION_ERROR both surface here as
      // err.message.
      setError(err.message);
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <Modal
      icon={Bot}
      title="Register agent"
      subtitle="Connect a new AI agent for SentinelX to monitor."
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
                <Loader2 className="h-3.5 w-3.5 animate-spin" /> Registering...
              </>
            ) : (
              <>
                Register agent <Plus className="h-3.5 w-3.5" />
              </>
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
          <input
            required
            autoFocus
            value={name}
            onChange={(e) => setName(e.target.value)}
            placeholder="Finance Assistant"
            className={FIELD_CLASS}
          />
        </div>

        <div>
          <div className="mb-1.5 flex items-center justify-between">
            <label className="text-xs font-medium text-white/65">Framework</label>
            <span className="text-[11px] text-white/45">Free text — pick a preset or type your own</span>
          </div>
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
          <input
            required
            value={framework}
            onChange={(e) => setFramework(e.target.value)}
            placeholder="e.g. CrewAI"
            className={FIELD_CLASS}
          />
        </div>

        <div>
          <label className="mb-1.5 block text-xs font-medium text-white/65">Framework version <span className="text-white/45">(optional)</span></label>
          <input
            value={frameworkVersion}
            onChange={(e) => setFrameworkVersion(e.target.value)}
            placeholder="1.2.0"
            className={`max-w-[10rem] ${FIELD_CLASS}`}
          />
        </div>

        <div>
          <label className="mb-1.5 block text-xs font-medium text-white/65">Description <span className="text-white/45">(optional)</span></label>
          <textarea
            value={description}
            onChange={(e) => setDescription(e.target.value)}
            rows={3}
            placeholder="What does this agent do?"
            className={`resize-none ${FIELD_CLASS}`}
          />
        </div>
      </div>
    </Modal>
  );
}

export default function Agents() {
  const { user } = useAuth();
  const [agents, setAgents] = useState(null);
  const [error, setError] = useState(null);
  const [status, setStatus] = useState("");
  const [showCreate, setShowCreate] = useState(false);

  // POST /v1/agents is Owner only server-side (403 for anyone else).
  const canCreate = user?.role === "OWNER";

  async function load(currentStatus = status) {
    setError(null);
    setAgents(null);
    try {
      const res = await listAgents(currentStatus ? { status: currentStatus } : {});
      setAgents(res.data);
    } catch (e) {
      setError(e.message);
    }
  }

  useEffect(() => {
    load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  function handleStatusChange(e) {
    const value = e.target.value;
    setStatus(value);
    load(value);
  }

  return (
    <div>
      <Topbar
        icon={Bot}
        title="Agents"
        subtitle="Every AI agent connected to SentinelX."
        actions={
          <button
            onClick={() => setShowCreate(true)}
            disabled={!canCreate}
            title={canCreate ? undefined : "Only workspace Owners can register agents"}
            className="flex items-center gap-1.5 rounded-lg bg-white px-4 py-2.5 text-sm font-semibold text-[#07080f] transition hover:bg-white/90 disabled:cursor-not-allowed disabled:opacity-40"
          >
            <Plus className="h-4 w-4" /> Register agent
          </button>
        }
      />

      <div className="mb-5 flex items-center gap-3">
        <select
          value={status}
          onChange={handleStatusChange}
          className="rounded-lg border border-white/[0.1] bg-[#0b0d17] px-3.5 py-2 text-xs text-white focus:border-indigo-400/50 focus:outline-none"
        >
          {STATUS_OPTIONS.map((o) => (
            <option key={o.value} value={o.value}>{o.label}</option>
          ))}
        </select>
      </div>

      {error && <PageError message={error} onRetry={() => load()} />}
      {!error && !agents && <PageLoader />}
      {!error && agents && agents.length === 0 && <EmptyState icon={Bot} message="No agents registered yet." />}

      {!error && agents && agents.length > 0 && (
        <Reveal>
          <GlassCard className="overflow-hidden">
            <div className="flex items-center justify-between border-b border-white/[0.08] px-6 py-4">
              <span className="flex items-center gap-2 text-sm font-medium text-white">
                <Bot className="h-4 w-4 text-indigo-400" /> All Agents
              </span>
              <span className="font-mono text-xs text-white/45">{agents.length} total</span>
            </div>
            <table className="w-full text-left text-sm">
              <thead>
                <tr className="border-b border-white/[0.06] text-xs text-white/50">
                  <th className="px-5 py-3.5 font-medium">Agent</th>
                  <th className="px-5 py-3.5 font-medium">Framework</th>
                  <th className="px-5 py-3.5 font-medium">Status</th>
                  <th className="px-5 py-3.5 font-medium">Last Seen</th>
                </tr>
              </thead>
              <tbody>
                {agents.map((a) => (
                  <tr key={a.id} className="border-b border-white/[0.04] transition hover:bg-white/[0.05]">
                    <td className="px-5 py-4">
                      <Link to={`/agents/${a.id}`} className="flex items-center gap-2.5 font-medium text-white">
                        <span className="flex h-7 w-7 items-center justify-center rounded-lg bg-indigo-500/10">
                          <Bot className="h-4 w-4 text-indigo-400" />
                        </span>
                        {a.name}
                      </Link>
                    </td>
                    <td className="px-5 py-4 text-white/65">
                      {a.framework}
                      {a.framework_version && <span className="text-white/40"> · v{a.framework_version}</span>}
                    </td>
                    <td className="px-5 py-4"><Badge tone={a.status?.toLowerCase()}>{a.status?.toLowerCase()}</Badge></td>
                    <td className="px-5 py-4 text-white/50">
                      {a.last_seen_at ? new Date(a.last_seen_at).toLocaleString() : "Never"}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </GlassCard>
        </Reveal>
      )}

      {showCreate && (
        <CreateAgentModal
          onClose={() => setShowCreate(false)}
          onCreated={() => {
            setShowCreate(false);
            load();
          }}
        />
      )}
    </div>
  );
}
