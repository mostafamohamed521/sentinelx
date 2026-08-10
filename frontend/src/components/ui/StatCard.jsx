import React from "react";
import { AreaChart, Area, ResponsiveContainer } from "recharts";
import { TrendingUp, TrendingDown } from "lucide-react";

const THEMES = {
  indigo: { text: "text-indigo-300", ring: "ring-indigo-500/20", glow: "bg-indigo-500/25", stroke: "#818cf8" },
  emerald: { text: "text-emerald-300", ring: "ring-emerald-500/20", glow: "bg-emerald-500/25", stroke: "#34d399" },
  amber: { text: "text-amber-300", ring: "ring-amber-500/20", glow: "bg-amber-500/25", stroke: "#fbbf24" },
  rose: { text: "text-rose-300", ring: "ring-rose-500/20", glow: "bg-rose-500/25", stroke: "#f87171" },
};

export default function StatCard({ label, value, trend, up, sub, icon: Icon, color = "indigo", spark }) {
  const theme = THEMES[color] || THEMES.indigo;

  return (
    <div
      className={`group relative overflow-hidden rounded-2xl border border-white/[0.12] bg-white/[0.05] p-5 ring-1 ${theme.ring} backdrop-blur-xl transition-all duration-300 hover:-translate-y-0.5 hover:bg-white/[0.07]`}
    >
      <div className={`pointer-events-none absolute -right-6 -top-10 h-28 w-28 rounded-full ${theme.glow} blur-3xl opacity-40 transition-opacity duration-300 group-hover:opacity-70`} />

      <div className="relative flex items-start justify-between">
        <div className="min-w-0">
          <div className="font-mono text-[10.5px] font-medium uppercase tracking-[0.14em] text-white/50">{label}</div>
          <div className="mt-2 flex items-baseline gap-2">
            <span className="font-display text-[1.95rem] font-semibold leading-none tracking-tight text-white">{value}</span>
            {trend && (
              <span className={`flex items-center gap-0.5 text-[11px] font-medium ${up ? "text-emerald-400" : "text-rose-400"}`}>
                {up ? <TrendingUp className="h-3 w-3" /> : <TrendingDown className="h-3 w-3" />}
                {trend}
              </span>
            )}
          </div>
          {sub && <div className="mt-1.5 truncate text-[11.5px] text-white/45">{sub}</div>}
        </div>
        {Icon && (
          <span className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-white/[0.08] bg-white/[0.04] ${theme.text} transition-transform duration-300 group-hover:scale-105`}>
            <Icon className="h-4 w-4" />
          </span>
        )}
      </div>

      {spark && (
        <div className="relative -mx-1 mt-3 h-8">
          <ResponsiveContainer width="100%" height="100%">
            <AreaChart data={spark.map((v, i) => ({ i, v }))} margin={{ top: 0, right: 0, left: 0, bottom: 0 }}>
              <defs>
                <linearGradient id={`spark-${color}`} x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stopColor={theme.stroke} stopOpacity={0.4} />
                  <stop offset="100%" stopColor={theme.stroke} stopOpacity={0} />
                </linearGradient>
              </defs>
              <Area type="monotone" dataKey="v" stroke={theme.stroke} strokeWidth={1.75} fill={`url(#spark-${color})`} />
            </AreaChart>
          </ResponsiveContainer>
        </div>
      )}
    </div>
  );
}
