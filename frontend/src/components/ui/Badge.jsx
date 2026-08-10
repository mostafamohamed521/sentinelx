import React from "react";

const styles = {
  low: "bg-emerald-500/15 text-emerald-300",
  medium: "bg-amber-500/15 text-amber-300",
  high: "bg-rose-500/15 text-rose-300",
  critical: "bg-rose-500/20 text-rose-300",
  neutral: "bg-white/[0.06] text-white/65",
  open: "bg-indigo-500/15 text-indigo-300",
  acknowledged: "bg-amber-500/15 text-amber-300",
  resolved: "bg-emerald-500/15 text-emerald-300",
  active: "bg-emerald-500/15 text-emerald-300",
  archived: "bg-white/[0.06] text-white/55",
};

export default function Badge({ tone = "neutral", children }) {
  return (
    <span className={`inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-medium ${styles[tone] || styles.neutral}`}>
      {children}
    </span>
  );
}
