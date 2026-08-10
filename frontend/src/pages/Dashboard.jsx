import React, { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { PieChart, Pie, Cell, ResponsiveContainer, Tooltip } from "recharts";
import Topbar from "../components/ui/Topbar.jsx";
import GlassCard from "../components/ui/GlassCard.jsx";
import StatCard from "../components/ui/StatCard.jsx";
import Badge from "../components/ui/Badge.jsx";
import Reveal from "../components/ui/Reveal.jsx";
import { PageLoader, PageError } from "../components/ui/PageState.jsx";
import { useCountUp } from "../hooks/useOnScreen.js";
import { getDashboard } from "../lib/api/dashboard.js";
import {
  LayoutDashboard, AlertTriangle, ArrowRight, Bot, ShieldAlert, CheckCircle2, Radar, ShieldCheck,
} from "lucide-react";

const RISK_COLORS = { SAFE: "#34d399", SUSPICIOUS: "#fbbf24", MALICIOUS: "#f87171" };
const SEVERITY_RANK = { CRITICAL: 3, HIGH: 2, MEDIUM: 1, LOW: 0 };

// The Dashboard's stats/recent items can change from actions the current
// user never took (another Agent's Observation completing analysis
// elsewhere) — a lightweight periodic refresh, not a one-shot fetch, so
// this view doesn't silently go stale for the length of the visit.
const REFRESH_INTERVAL_MS = 60000;

// A single, honest read of organization posture — derived entirely from
// the same organization_stats/risk_summary the stat cards already show,
// never a separate or fabricated signal. Malicious verdicts outrank open
// alerts because a verdict is a completed finding; an alert can still be
// open for something ultimately benign.
const STATUS_TIERS = {
  critical: { label: "Critical Risk Detected", dot: "bg-rose-400", ring: "ring-rose-400/40", text: "text-rose-300" },
  elevated: { label: "Monitoring Active Alerts", dot: "bg-amber-400", ring: "ring-amber-400/40", text: "text-amber-300" },
  clear: { label: "All Systems Nominal", dot: "bg-emerald-400", ring: "ring-emerald-400/40", text: "text-emerald-300" },
};

function systemStatus(stats, riskSummary) {
  if (riskSummary.MALICIOUS > 0) {
    return { tier: "critical", detail: `${riskSummary.MALICIOUS} malicious verdict${riskSummary.MALICIOUS === 1 ? "" : "s"} in the last 30 days.` };
  }
  if (stats.open_alerts > 0) {
    return { tier: "elevated", detail: `${stats.open_alerts} alert${stats.open_alerts === 1 ? "" : "s"} awaiting review.` };
  }
  return { tier: "clear", detail: "No open alerts or malicious verdicts right now." };
}

function CustomTooltip({ active, payload, label }) {
  if (!active || !payload?.length) return null;
  return (
    <div className="rounded-lg border border-white/10 bg-[#0b0d17] px-3 py-2 font-mono text-xs shadow-xl">
      {label && <div className="mb-1 text-white/55">{label}</div>}
      {payload.map((p) => (
        <div key={p.dataKey} className="text-white/80">
          {p.name}: <span className="font-semibold">{p.value}</span>
        </div>
      ))}
    </div>
  );
}

function SectionLabel({ children }) {
  return (
    <div className="mb-3 flex items-center gap-2 font-mono text-[10.5px] font-medium uppercase tracking-[0.14em] text-white/40">
      <span className="h-px w-4 bg-white/15" />
      {children}
    </div>
  );
}

export default function Dashboard() {
  const [dash, setDash] = useState(null);
  const [error, setError] = useState(null);

  async function load() {
    setError(null);
    setDash(null);
    try {
      setDash(await getDashboard());
    } catch (e) {
      setError(e.message);
    }
  }

  useEffect(() => {
    load();
    // Background refresh only — does not reset dash to null, so a periodic
    // tick never flashes the page back to a loading state.
    const intervalId = setInterval(() => {
      getDashboard().then(setDash).catch((e) => setError(e.message));
    }, REFRESH_INTERVAL_MS);
    return () => clearInterval(intervalId);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const totalAgents = useCountUp(dash?.organization_stats.total_agents || 0);
  const totalObs = useCountUp(dash?.organization_stats.total_observations_last_30_days || 0, 1600);
  const openAlerts = useCountUp(dash?.organization_stats.open_alerts || 0, 1000);

  if (error) return <PageError message={error} onRetry={load} />;
  if (!dash) return <PageLoader />;

  const { organization_stats: stats, risk_summary: riskSummary, recent_alerts: recentAlerts, active_agents: activeAgents } = dash;
  const status = systemStatus(stats, riskSummary);
  const statusTheme = STATUS_TIERS[status.tier];

  const riskPie = [
    { name: "Safe", key: "SAFE", value: riskSummary.SAFE },
    { name: "Suspicious", key: "SUSPICIOUS", value: riskSummary.SUSPICIOUS },
    { name: "Malicious", key: "MALICIOUS", value: riskSummary.MALICIOUS },
  ];
  const hasRiskData = riskPie.some((r) => r.value > 0);

  // recent_alerts carries only { id, severity, status, created_at, reasons }
  // — no risk_score/confidence at this level (those live under the nested
  // `prediction` on GET /alerts/{id}) — so the spotlight is chosen by
  // severity rank rather than a numeric score.
  const spotlightAlert = [...recentAlerts].sort(
    (a, b) => (SEVERITY_RANK[b.severity] ?? -1) - (SEVERITY_RANK[a.severity] ?? -1)
  )[0];

  return (
    <div>
      <Topbar icon={LayoutDashboard} title="Dashboard" subtitle="Everything happening across your agents, right now." />

      {/* System Pulse — the one signature moment on this page: the whole
          organization's posture collapsed into a single glanceable read,
          derived from the same real stats the cards below break out in
          detail, not a separate metric. */}
      <Reveal>
        <div className="relative mb-8 overflow-hidden rounded-2xl border border-white/[0.08] bg-white/[0.03] px-6 py-5 backdrop-blur-xl">
          <div
            className={`pointer-events-none absolute -left-24 -top-24 h-64 w-64 rounded-full opacity-25 blur-3xl ${
              status.tier === "critical" ? "bg-rose-500" : status.tier === "elevated" ? "bg-amber-500" : "bg-emerald-500"
            }`}
          />
          <div className="relative flex flex-wrap items-center justify-between gap-4">
            <div className="flex items-center gap-4">
              <span className={`relative flex h-3 w-3 shrink-0`}>
                <span className={`absolute inline-flex h-full w-full animate-ping rounded-full ${statusTheme.dot} opacity-60 motion-reduce:animate-none`} />
                <span className={`relative inline-flex h-3 w-3 rounded-full ${statusTheme.dot} ring-4 ${statusTheme.ring}`} />
              </span>
              <div>
                <div className={`font-display text-base font-semibold ${statusTheme.text}`}>{STATUS_TIERS[status.tier].label}</div>
                <div className="mt-0.5 text-xs text-white/55">{status.detail}</div>
              </div>
            </div>
            <div className="flex items-center gap-2 font-mono text-[11px] text-white/40">
              <ShieldCheck className="h-3.5 w-3.5" />
              refreshed every {REFRESH_INTERVAL_MS / 1000}s
            </div>
          </div>
        </div>
      </Reveal>

      <div className="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Reveal><StatCard label="Total Agents" value={Math.round(totalAgents)} sub={`${stats.active_agents} active`} icon={Bot} color="indigo" /></Reveal>
        <Reveal delay={60}><StatCard label="Observations (30d)" value={totalObs >= 1000 ? `${(totalObs / 1000).toFixed(1)}K` : Math.round(totalObs)} icon={Radar} color="emerald" /></Reveal>
        <Reveal delay={120}><StatCard label="Open Alerts" value={Math.round(openAlerts)} icon={AlertTriangle} color="amber" /></Reveal>
        <Reveal delay={180}><StatCard label="Malicious Verdicts" value={riskSummary.MALICIOUS} sub="Across all analyzed observations" icon={ShieldAlert} color="rose" /></Reveal>
      </div>

      <SectionLabel>Live Overview</SectionLabel>
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <Reveal>
          {spotlightAlert ? (
            <Link to={`/alerts/${spotlightAlert.id}`} className="block h-full">
              <GlassCard rounded="3xl" className="group relative flex h-full flex-col overflow-hidden border-rose-500/25 p-6 transition-all duration-300 hover:-translate-y-0.5 hover:border-rose-500/40">
                <div className="pointer-events-none absolute -right-10 -top-16 h-48 w-48 rounded-full bg-rose-500/20 blur-3xl transition-opacity duration-300 group-hover:opacity-70" />
                <div className="relative mb-5 flex items-center justify-between">
                  <span className="flex items-center gap-2 text-sm font-medium text-white">
                    <ShieldAlert className="h-4 w-4 text-rose-400" /> Needs Attention
                  </span>
                  <ArrowRight className="h-3.5 w-3.5 text-white/40 transition-transform duration-300 group-hover:translate-x-0.5" />
                </div>
                <Badge tone={spotlightAlert.severity?.toLowerCase()}>{spotlightAlert.severity?.toLowerCase()}</Badge>
                <p className="relative mt-3 text-sm leading-relaxed text-white/70">{spotlightAlert.reasons?.[0] || "Flagged behavior"}</p>
                <div className="relative mt-auto pt-6 font-mono text-[11px] text-white/45">
                  {new Date(spotlightAlert.created_at).toLocaleString()}
                </div>
              </GlassCard>
            </Link>
          ) : (
            <GlassCard rounded="3xl" className="flex h-full flex-col items-center justify-center gap-2 p-6 text-center">
              <span className="flex h-11 w-11 items-center justify-center rounded-full bg-emerald-500/10">
                <CheckCircle2 className="h-5 w-5 text-emerald-400" />
              </span>
              <div className="text-sm text-white/65">No open alerts right now</div>
            </GlassCard>
          )}
        </Reveal>

        <Reveal delay={80}>
          <GlassCard rounded="3xl" className="flex h-full flex-col p-6 transition-all duration-300 hover:-translate-y-0.5">
            <div className="mb-5 flex items-center gap-2 text-sm font-medium text-white">
              <Radar className="h-4 w-4 text-indigo-400" /> Risk Distribution
            </div>
            {hasRiskData ? (
              <div className="flex flex-1 items-center gap-5">
                <div className="h-28 w-28 shrink-0">
                  <ResponsiveContainer width="100%" height="100%">
                    <PieChart>
                      <Tooltip content={<CustomTooltip />} />
                      <Pie data={riskPie} dataKey="value" nameKey="name" innerRadius={38} outerRadius={54} paddingAngle={3} strokeWidth={0}>
                        {riskPie.map((entry) => (
                          <Cell key={entry.key} fill={RISK_COLORS[entry.key]} />
                        ))}
                      </Pie>
                    </PieChart>
                  </ResponsiveContainer>
                </div>
                <div className="flex-1 space-y-2 text-xs">
                  {riskPie.map((r) => (
                    <div key={r.key} className="flex items-center justify-between">
                      <span className="flex items-center gap-1.5 text-white/55">
                        <span className="h-2 w-2 rounded-full" style={{ backgroundColor: RISK_COLORS[r.key] }} />
                        {r.name}
                      </span>
                      <span className="font-mono font-medium" style={{ color: RISK_COLORS[r.key] }}>{r.value}</span>
                    </div>
                  ))}
                </div>
              </div>
            ) : (
              <div className="flex flex-1 items-center justify-center text-sm text-white/45">No analyzed observations yet.</div>
            )}
          </GlassCard>
        </Reveal>

        <Reveal delay={160}>
          <GlassCard rounded="3xl" className="p-6 transition-all duration-300 hover:-translate-y-0.5">
            <div className="mb-5 flex items-center justify-between">
              <span className="flex items-center gap-2 text-sm font-medium text-white">
                <Bot className="h-4 w-4 text-indigo-400" /> Active Agents
              </span>
              <Link to="/agents" className="flex items-center gap-1 text-xs font-medium text-indigo-400 hover:text-indigo-300">
                View all <ArrowRight className="h-3 w-3" />
              </Link>
            </div>
            <div className="divide-y divide-white/[0.05]">
              {activeAgents.length === 0 && <p className="py-2.5 text-sm text-white/45">No active agents yet.</p>}
              {activeAgents.map((a) => (
                <Link
                  key={a.id}
                  to={`/agents/${a.id}`}
                  className="flex items-center justify-between py-2.5 text-sm transition first:pt-0 last:pb-0 hover:text-white"
                >
                  <span className="flex items-center gap-2.5 text-white/60">
                    <Bot className="h-3.5 w-3.5 text-indigo-400" />
                    {a.name}
                  </span>
                  <Badge tone={a.status?.toLowerCase()}>{a.status?.toLowerCase()}</Badge>
                </Link>
              ))}
            </div>
          </GlassCard>
        </Reveal>
      </div>
    </div>
  );
}
