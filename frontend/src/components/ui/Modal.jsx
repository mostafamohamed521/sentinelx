import React from "react";
import { createPortal } from "react-dom";
import { X } from "lucide-react";

// A modal needs to read clearly against a dimmed backdrop, so unlike
// GlassCard elsewhere in the app, this uses a solid panel background
// rather than a translucent one — layering two levels of transparency
// (the dark backdrop behind a near-transparent card) was what made prior
// modals look washed out and low-contrast.
//
// Header and footer are pinned; only the middle content scrolls, so the
// primary action is always reachable without hunting for it. The panel
// always fills the available viewport height (minus a small margin) rather
// than shrinking to its content — flexible in the sense that it adapts to
// whatever screen size it's on (desktop or mobile), while the internal
// body scrolls automatically if the fields don't fit that height.
//
// Rendered through a portal straight into document.body rather than in
// place in the component tree. `position: fixed` is normally relative to
// the viewport, but any ancestor with a `transform`, `filter`, or
// `will-change` value (even a no-op one, e.g. `translateY(0)` left behind
// by a finished CSS animation) creates a new *containing block*, silently
// re-scoping every `fixed` descendant to that ancestor's box instead of
// the real viewport — which is exactly what was constraining this modal.
// Portaling to <body> makes the modal immune to that entire bug class
// permanently, regardless of what styling any future ancestor picks up.
export default function Modal({ icon: Icon, title, subtitle, onClose, onSubmit, children, footer, maxWidth = "max-w-lg" }) {
  const Container = onSubmit ? "form" : "div";

  return createPortal(
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-3 backdrop-blur-sm sm:p-4">
      <Container
        {...(onSubmit ? { onSubmit } : {})}
        className={`flex h-[calc(100dvh-1.5rem)] w-full ${maxWidth} flex-col overflow-hidden rounded-2xl border border-white/[0.1] bg-[#0b0d17] shadow-[0_24px_80px_-20px_rgba(0,0,0,0.85)]`}
      >
        <div className="flex shrink-0 items-start justify-between border-b border-white/[0.08] px-6 py-5">
          <div className="flex min-w-0 items-center gap-3">
            {Icon && (
              <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-500/10 ring-1 ring-indigo-500/20">
                <Icon className="h-5 w-5 text-indigo-400" />
              </span>
            )}
            <div className="min-w-0">
              <h2 className="font-display text-base font-semibold text-white">{title}</h2>
              {subtitle && <p className="mt-0.5 truncate text-xs text-white/55">{subtitle}</p>}
            </div>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="shrink-0 rounded-lg p-1.5 text-white/55 transition hover:bg-white/[0.08] hover:text-white"
          >
            <X className="h-4 w-4" />
          </button>
        </div>

        <div className="flex-1 overflow-y-auto px-6 py-5">{children}</div>

        {footer && (
          <div className="flex shrink-0 items-center gap-3 border-t border-white/[0.08] bg-[#0b0d17] px-6 py-5">
            {footer}
          </div>
        )}
      </Container>
    </div>,
    document.body
  );
}
