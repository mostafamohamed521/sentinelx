import React from "react";
import { AlertCircle, RefreshCw } from "lucide-react";
import Logo from "./Logo.jsx";

export function PageLoader() {
  return (
    <div className="flex min-h-[40vh] flex-col items-center justify-center gap-3">
      <div className="animate-pulse">
        <Logo size={36} />
      </div>
      <span className="text-xs text-white/45">Loading...</span>
    </div>
  );
}

export function PageError({ message, onRetry }) {
  return (
    <div className="flex min-h-[40vh] flex-col items-center justify-center gap-3 text-center">
      <div className="flex h-12 w-12 items-center justify-center rounded-full bg-rose-500/10">
        <AlertCircle className="h-6 w-6 text-rose-400" />
      </div>
      <div className="text-sm text-white/60">{message || "Something went wrong"}</div>
      {onRetry && (
        <button
          onClick={onRetry}
          className="flex items-center gap-1.5 rounded-lg border border-white/[0.1] px-4 py-2 text-xs font-medium text-white/70 transition hover:bg-white/[0.05]"
        >
          <RefreshCw className="h-3.5 w-3.5" /> Try again
        </button>
      )}
    </div>
  );
}

export function EmptyState({ icon: Icon, message }) {
  return (
    <div className="flex min-h-[30vh] flex-col items-center justify-center gap-2 text-center">
      {Icon && <Icon className="h-6 w-6 text-white/35" />}
      <div className="text-sm text-white/45">{message}</div>
    </div>
  );
}
