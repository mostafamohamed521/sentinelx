import React, { useEffect, useState } from "react";
import Topbar from "../components/ui/Topbar.jsx";
import GlassCard from "../components/ui/GlassCard.jsx";
import Reveal from "../components/ui/Reveal.jsx";
import { PageLoader, PageError, EmptyState } from "../components/ui/PageState.jsx";
import { useAuth } from "../lib/AuthContext.jsx";
import { listAuditLogs, listSecurityLogs } from "../lib/api/audit.js";
import { ScrollText, ShieldCheck, ChevronLeft, ChevronRight, Lock } from "lucide-react";

const TABS = [
  { id: "all", label: "All Activity" },
  { id: "security", label: "Security" },
];

export default function AuditLogs() {
  const { user } = useAuth();
  const [tab, setTab] = useState("all");
  const [entries, setEntries] = useState(null);
  const [pagination, setPagination] = useState(null);
  const [page, setPage] = useState(1);
  const [error, setError] = useState(null);

  const [filters, setFilters] = useState({ action: "", resource_type: "" });

  // GET /v1/audit-logs and GET /v1/security-logs are Owner/Admin only —
  // a Member gets a 403 server-side. Gated here so a Member landing on
  // this URL directly (e.g. a stale bookmark) gets an explanatory message
  // instead of a failed request.
  const allowed = user?.role === "OWNER" || user?.role === "ADMIN";

  async function load() {
    if (!allowed) return;
    setError(null);
    setEntries(null);
    try {
      const params = { page, per_page: 20, ...filters };
      const res = tab === "security" ? await listSecurityLogs(params) : await listAuditLogs(params);
      setEntries(res.data);
      setPagination(res.pagination);
    } catch (e) {
      setError(e.message);
    }
  }

  useEffect(() => {
    load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [tab, page]);

  function applyFilters(e) {
    e.preventDefault();
    setPage(1);
    load();
  }

  if (!allowed) {
    return (
      <div>
        <Topbar icon={ScrollText} title="Audit Logs" subtitle="Organization activity and security events." />
        <div className="flex min-h-[40vh] flex-col items-center justify-center gap-3 text-center">
          <div className="flex h-12 w-12 items-center justify-center rounded-full bg-white/[0.06]">
            <Lock className="h-6 w-6 text-white/45" />
          </div>
          <div className="text-sm text-white/65">Audit and security logs are visible to Owners and Admins only.</div>
        </div>
      </div>
    );
  }

  return (
    <div>
      <Topbar
        icon={ScrollText}
        title="Audit Logs"
        subtitle="Every recorded action across your organization."
      />

      <div className="mb-5 flex gap-1 rounded-lg border border-white/[0.12] bg-white/[0.04] p-1 w-fit">
        {TABS.map((t) => (
          <button
            key={t.id}
            onClick={() => {
              setTab(t.id);
              setPage(1);
            }}
            className={`flex items-center gap-1.5 rounded-md px-3.5 py-1.5 text-xs font-medium transition ${
              tab === t.id ? "bg-white/[0.08] text-white" : "text-white/55 hover:text-white/70"
            }`}
          >
            {t.id === "security" && <ShieldCheck className="h-3.5 w-3.5" />}
            {t.label}
          </button>
        ))}
      </div>

      <GlassCard className="mb-6 p-4">
        <form onSubmit={applyFilters} className="flex flex-wrap items-end gap-3">
          <div>
            <label className="mb-1 block text-[11px] font-medium text-white/55">Action</label>
            <input
              value={filters.action}
              onChange={(e) => setFilters((f) => ({ ...f, action: e.target.value }))}
              placeholder="e.g. agent.created"
              className="rounded-md border border-white/[0.14] bg-white/[0.05] px-3 py-1.5 text-xs text-white placeholder:text-white/35 focus:border-indigo-400/50 focus:outline-none"
            />
          </div>
          <div>
            <label className="mb-1 block text-[11px] font-medium text-white/55">Resource type</label>
            <input
              value={filters.resource_type}
              onChange={(e) => setFilters((f) => ({ ...f, resource_type: e.target.value }))}
              placeholder="e.g. Agent"
              className="rounded-md border border-white/[0.14] bg-white/[0.05] px-3 py-1.5 text-xs text-white placeholder:text-white/35 focus:border-indigo-400/50 focus:outline-none"
            />
          </div>
          <button
            type="submit"
            className="rounded-md border border-white/[0.1] px-3.5 py-1.5 text-xs font-medium text-white/70 transition hover:bg-white/[0.05]"
          >
            Apply filters
          </button>
        </form>
      </GlassCard>

      {error && <PageError message={error} onRetry={load} />}
      {!error && !entries && <PageLoader />}
      {!error && entries && entries.length === 0 && (
        <EmptyState icon={ScrollText} message="No matching log entries." />
      )}

      {!error && entries && entries.length > 0 && (
        <Reveal>
          <GlassCard className="overflow-hidden">
            <div className="flex items-center justify-between border-b border-white/[0.08] px-6 py-4">
              <span className="flex items-center gap-2 text-sm font-medium text-white">
                <ScrollText className="h-4 w-4 text-indigo-400" /> Log Entries
              </span>
              <span className="font-mono text-xs text-white/45">{entries.length} shown</span>
            </div>
            <table className="w-full text-left text-sm">
              <thead>
                <tr className="border-b border-white/[0.06] text-xs text-white/50">
                  <th className="px-5 py-3.5 font-medium">Action</th>
                  <th className="px-5 py-3.5 font-medium">Resource</th>
                  <th className="px-5 py-3.5 font-medium">Date</th>
                </tr>
              </thead>
              <tbody>
                {entries.map((e) => (
                  <tr key={e.id} className="border-b border-white/[0.04] transition hover:bg-white/[0.05]">
                    <td className="px-5 py-4 font-mono text-xs text-white/80">{e.action}</td>
                    <td className="px-5 py-4 text-white/65">
                      {e.resource_type}
                      {e.resource_id && <span className="text-white/40"> · {e.resource_id}</span>}
                    </td>
                    <td className="px-5 py-4 font-mono text-xs text-white/50">{new Date(e.created_at).toLocaleString()}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </GlassCard>

          {pagination && pagination.total_pages > 1 && (
            <div className="mt-4 flex items-center justify-between text-xs text-white/55">
              <span>
                Page {pagination.page} of {pagination.total_pages} · {pagination.total_items} entries
              </span>
              <div className="flex gap-2">
                <button
                  onClick={() => setPage((p) => Math.max(1, p - 1))}
                  disabled={pagination.page <= 1}
                  className="flex items-center gap-1 rounded-md border border-white/[0.1] px-2.5 py-1.5 disabled:opacity-40"
                >
                  <ChevronLeft className="h-3.5 w-3.5" /> Prev
                </button>
                <button
                  onClick={() => setPage((p) => Math.min(pagination.total_pages, p + 1))}
                  disabled={pagination.page >= pagination.total_pages}
                  className="flex items-center gap-1 rounded-md border border-white/[0.1] px-2.5 py-1.5 disabled:opacity-40"
                >
                  Next <ChevronRight className="h-3.5 w-3.5" />
                </button>
              </div>
            </div>
          )}
        </Reveal>
      )}
    </div>
  );
}
