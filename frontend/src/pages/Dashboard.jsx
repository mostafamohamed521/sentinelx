import React, { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import {
  AreaChart, Area, PieChart, Pie, Cell, ResponsiveContainer, Tooltip,
} from "recharts";
import Topbar from "../components/ui/Topbar.jsx";
import GlassCard from "../components/ui/GlassCard.jsx";
import StatCard from "../components/ui/StatCard.jsx";
import Badge from "../components/ui/Badge.jsx";
import Reveal from "../components/ui/Reveal.jsx";
import { PageLoader, PageError } from "../components/ui/PageState.jsx";
import { useCountUp } from "../hooks/useOnScreen.js";
import { getDashboard } from "../lib/api/dashboard.js";
import { topThreats } from "../lib/mockDb.js";
import {
  LayoutDashboard, AlertTriangle, ArrowRight, Bot, ShieldAlert, CheckCircle2, Radar,
} from "lucide-react";

const RISK_COLORS = { SAFE: "#34d399", SUSPICIOUS: "#fbbf24", MALICIOUS: "#f87171" };
const SEVERITY_RANK = { CRITICAL: 3, HIGH: 2, MEDIUM: 1, LOW: 0 };

// The Dashboard's stats/recent items can change from actions the current
// user never took (another Agent's Observation completing analysis
// elsewhere) — a lightweight periodic refresh, not a one-shot fetch, so
// this view doesn't silently go stale for the length of the visit.
const REFRESH_INTERVAL_MS = 60000;

function CustomTooltip({ active, payload, label }) {
  if (!active || !payload?.length) return null;
  return (
    <div className="rounded-lg border border-white/10 bg-[#0b0d17] px-3 py-2 text-xs shadow-xl">
      {label && <div className="mb-1 text-white/40">{label}</div>}
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
    <div className="mb-3 flex items-center gap-2 text-[11px] font-semibold uppercase tracking-wider text-white/25">
      <span className="h-px w-4 bg-white/15" />
      {children}
    </div>
  );
}

export default function Dashboard() {
  const [dash, setDash] = useState(null);
  const [error, setError] = useState(null);
  const [tab, setTab] = useState("requests");

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

  // No time-series endpoint exists in the provided contract for either of
  // these — this trend is illustrative only, not sourced from real data.
  const trend = Array.from({ length: 14 }).map((_, i) => ({
    day: `D${i + 1}`,
    requests: Math.round(60 + Math.sin(i / 2) * 20 + i * 1.5 + (i % 3 === 0 ? 15 : 0)),
    risk: Math.round(15 + Math.cos(i / 3) * 8 + (i > 9 ? (i - 9) * 3 : 0)),
  }));

  const sparkA = [40, 44, 42, 50, 48, 55, 60];
  const sparkB = [18.2, 17.6, 18.9, 18.1, 18.4, 18.0, 18.4];
  const sparkC = [9, 8, 10, 7, 9, 8, 7];
  const sparkD = [30, 28, 26, 25, 24, 23, 23];

  const riskPie = [
    { name: "Safe", key: "SAFE", value: riskSummary.SAFE },
    { name: "Suspicious", key: "SUSPICIOUS", value: riskSummary.SUSPICIOUS },
    { name: "Malicious", key: "MALICIOUS", value: riskSummary.MALICIOUS },
  ];

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

      <div className="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Reveal><StatCard label="Total Agents" value={Math.round(totalAgents)} sub={`${stats.active_agents} active`} icon={Bot} color="indigo" spark={sparkA} /></Reveal>
        <Reveal delay={60}><StatCard label="Observations (30d)" value={`${(totalObs / 1000).toFixed(1)}K`} icon={Radar} color="emerald" spark={sparkB} /></Reveal>
        <Reveal delay={120}><StatCard label="Open Alerts" value={Math.round(openAlerts)} icon={AlertTriangle} color="amber" spark={sparkC} /></Reveal>
        <Reveal delay={180}><StatCard label="Malicious Verdicts" value={riskSummary.MALICIOUS} sub="Across all analyzed observations" icon={ShieldAlert} color="rose" spark={sparkD} /></Reveal>
      </div>

      <SectionLabel>Live Signal</SectionLabel>
      <div className="mb-8 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <Reveal className="lg:col-span-2">
          <GlassCard rounded="3xl" className="p-6">
            <div className="mb-4 flex items-center justify-between">
              <div className="flex gap-1 rounded-lg border border-white/[0.08] bg-white/[0.02] p-1">
                {[{ id: "requests", label: "Requests" }, { id: "risk", label: "Risk Trend" }].map((t) => (
                  <button
                    key={t.id}
                    onClick={() => setTab(t.id)}
                    className={`rounded-md px-3 py-1.5 text-xs font-medium transition ${
                      tab === t.id ? "bg-white/[0.08] text-white" : "text-white/35 hover:text-white/60"
                    }`}
                  >
                    {t.label}
                  </button>
                ))}
              </div>
              {/* No time-series endpoint is part of the provided contract —
                  labeled honestly rather than presented as live data. */}
              <span className="text-[11px] text-white/25">Last 14 days · simulated</span>
            </div>
            <div className="h-56">
              <ResponsiveContainer width="100%" height="100%">
                <AreaChart data={trend} margin={{ top: 8, right: 8, left: 8, bottom: 0 }}>
                  <defs>
                    <linearGradient id="reqGrad" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="0%" stopColor={tab === "requests" ? "#818cf8" : "#f87171"} stopOpacity={0.35} />
                      <stop offset="100%" stopColor={tab === "requests" ? "#818cf8" : "#f87171"} stopOpacity={0} />
                    </linearGradient>
                  </defs>
                  <Tooltip content={<CustomTooltip />} cursor={{ stroke: "rgba(255,255,255,0.1)" }} />
                  <Area
                    type="monotone"
                    dataKey={tab === "requests" ? "requests" : "risk"}
                    name={tab === "requests" ? "Requests" : "Risk Score"}
                    stroke={tab === "requests" ? "#818cf8" : "#f87171"}
                    strokeWidth={2.5}
                    fill="url(#reqGrad)"
                  />
                </AreaChart>
              </ResponsiveContainer>
            </div>
          </GlassCard>
        </Reveal>

        <Reveal delay={100}>
          {spotlightAlert ? (
            <Link to={`/alerts/${spotlightAlert.id}`} className="block h-full">
              <GlassCard rounded="3xl" className="relative flex h-full flex-col overflow-hidden border-rose-500/25 p-6">
                <div className="pointer-events-none absolute -right-10 -top-16 h-48 w-48 rounded-full bg-rose-500/20 blur-3xl" />
                <div className="relative mb-3 flex items-center justify-between">
                  <Badge tone={spotlightAlert.severity?.toLowerCase()}>{spotlightAlert.severity?.toLowerCase()} · needs review</Badge>
                  <ArrowRight className="h-3.5 w-3.5 text-white/25" />
                </div>
                <p className="relative mt-1.5 text-sm leading-relaxed text-white/70">{spotlightAlert.reasons?.[0] || "Flagged behavior"}</p>
                <div className="relative mt-auto pt-6 text-xs text-white/30">
                  {new Date(spotlightAlert.created_at).toLocaleString()}
                </div>
              </GlassCard>
            </Link>
          ) : (
            <GlassCard rounded="3xl" className="flex h-full flex-col items-center justify-center gap-2 p-6 text-center">
              <CheckCircle2 className="h-6 w-6 text-emerald-400" />
              <div className="text-sm text-white/50">No critical alerts right now</div>
            </GlassCard>
          )}
        </Reveal>
      </div>

      <SectionLabel>Breakdown</SectionLabel>
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <Reveal>
          <GlassCard rounded="3xl" className="p-6">
            <div className="mb-4 text-sm font-medium text-white">Risk Distribution</div>
            <div className="flex items-center gap-5">
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
                    <span className="flex items-center gap-1.5 text-white/40">
                      <span className="h-2 w-2 rounded-full" style={{ backgroundColor: RISK_COLORS[r.key] }} />
                      {r.name}
                    </span>
                    <span className="font-medium" style={{ color: RISK_COLORS[r.key] }}>{r.value}</span>
                  </div>
                ))}
              </div>
            </div>
          </GlassCard>
        </Reveal>

        <Reveal delay={80}>
          <GlassCard rounded="3xl" className="p-6">
            <div className="mb-4 flex items-center justify-between">
              <span className="text-sm font-medium text-white">Top Threats</span>
              {/* No endpoint in the provided contract aggregates threats by
                  name — this list is sample data, not sourced from the API. */}
              <span className="text-[11px] text-white/30">Sample</span>
            </div>
            <div className="divide-y divide-white/[0.05]">
              {topThreats.map((t) => (
                <div key={t.name} className="flex items-center justify-between py-2.5 text-sm first:pt-0 last:pb-0">
                  <span className="text-white/60">{t.name}</span>
                  <span className="flex items-center gap-2">
                    <span className="text-white/35">{t.n}</span>
                    <Badge tone={t.sev}>{t.sev}</Badge>
                  </span>
                </div>
              ))}
            </div>
          </GlassCard>
        </Reveal>

        <Reveal delay={160}>
          <GlassCard rounded="3xl" className="p-6">
            <div className="mb-4 flex items-center justify-between">
              <span className="text-sm font-medium text-white">Active Agents</span>
              <Link to="/agents" className="flex items-center gap-1 text-xs font-medium text-indigo-400 hover:text-indigo-300">
                View all <ArrowRight className="h-3 w-3" />
              </Link>
            </div>
            <div className="divide-y divide-white/[0.05]">
              {activeAgents.length === 0 && <p className="py-2.5 text-sm text-white/30">No active agents yet.</p>}
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
