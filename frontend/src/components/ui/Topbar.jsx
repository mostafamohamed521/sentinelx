import React from "react";
import Breadcrumb from "./Breadcrumb.jsx";

export default function Topbar({ title, subtitle, actions, icon: Icon, showBreadcrumb = true }) {
  return (
    <div className="-mx-8 mb-8 border-b border-white/[0.1] bg-white/[0.04] px-8 pb-5 pt-6 backdrop-blur-xl">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div>
          {showBreadcrumb && <Breadcrumb />}
          <div className="flex items-center gap-3">
            {Icon && (
              <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-white/[0.08] bg-indigo-500/10">
                <Icon className="h-4 w-4 text-indigo-400" />
              </span>
            )}
            <h1 className="font-display text-2xl font-semibold tracking-tight text-white">{title}</h1>
          </div>
          {subtitle && <p className="mt-1.5 text-sm text-white/55">{subtitle}</p>}
        </div>
        {actions && <div className="flex items-center gap-3">{actions}</div>}
      </div>
    </div>
  );
}
