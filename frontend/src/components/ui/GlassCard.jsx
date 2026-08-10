import React from "react";

const ROUNDED = {
  xl: "rounded-xl",
  "2xl": "rounded-2xl",
  "3xl": "rounded-3xl",
};

export default function GlassCard({ children, className = "", rounded = "2xl", ...rest }) {
  return (
    <div
      className={`${ROUNDED[rounded] || ROUNDED["2xl"]} border border-white/[0.12] bg-white/[0.05] backdrop-blur-xl shadow-[0_0_0_1px_rgba(255,255,255,0.03),0_20px_60px_-20px_rgba(0,0,0,0.6)] ${className}`}
      {...rest}
    >
      {children}
    </div>
  );
}
