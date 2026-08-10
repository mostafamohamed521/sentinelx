import React, { useEffect, useState } from "react";
import {
  Lock, Eye, Wifi, KeyRound, Fingerprint, Radar, ShieldCheck, Terminal, ScanFace, Bug,
} from "lucide-react";

const SECURITY_ICONS = [Lock, Eye, Wifi, KeyRound, Fingerprint, Radar, ShieldCheck, Terminal, ScanFace, Bug];

/**
 * Cinematic one-time welcome sequence.
 *
 * Sequence: void -> shield draws itself with light -> particles converge ->
 * wordmark types in -> tagline fades -> shield FLIES to the header's logo
 * position (top-left) while everything else dissolves -> app reveals with
 * its own nav logo already sitting exactly where the shield landed.
 *
 * Usage: wrap the app root once. Calls onDone() when finished (or immediately
 * if skipped).
 */
export default function WelcomeIntro({ onDone }) {
  const [phase, setPhase] = useState(0);
  // 0 void, 1 draw shield, 2 particles converge, 3 wordmark, 4 tagline, 5 fly to header, 6 gone
  const [skipped, setSkipped] = useState(false);

  useEffect(() => {
    const timers = [
      setTimeout(() => setPhase(1), 250),
      setTimeout(() => setPhase(2), 1400),
      setTimeout(() => setPhase(3), 2300),
      setTimeout(() => setPhase(4), 3100),
      setTimeout(() => setPhase(5), 4200), // shield starts flying to header
      setTimeout(() => setPhase(6), 5000), // everything else is gone
      setTimeout(() => onDone?.(), 5150),
    ];
    return () => timers.forEach(clearTimeout);
  }, [onDone]);

  function handleSkip() {
    setSkipped(true);
    onDone?.();
  }

  const wordmark = "SentinelX";
  const flying = phase >= 5;

  // Target position roughly matches Nav's logo: px-6 (24px) + half of h-8 icon (16px) => ~40px from left,
  // py-4 (16px) container + half of nav height (~32px) => ~32px from top.
  const shieldStyle = flying
    ? {
        left: "40px",
        top: "32px",
        width: "32px",
        height: "32px",
        transform: "translate(-50%, -50%)",
        transition: "left 0.75s cubic-bezier(.65,0,.35,1), top 0.75s cubic-bezier(.65,0,.35,1), width 0.75s cubic-bezier(.65,0,.35,1), height 0.75s cubic-bezier(.65,0,.35,1)",
      }
    : {
        left: "50%",
        top: "50%",
        width: "96px",
        height: "96px",
        transform: "translate(-50%, -76px)",
        transition: "left 0.75s cubic-bezier(.65,0,.35,1), top 0.75s cubic-bezier(.65,0,.35,1), width 0.75s cubic-bezier(.65,0,.35,1), height 0.75s cubic-bezier(.65,0,.35,1)",
      };

  return (
    <div
      className="fixed inset-0 z-[100] bg-[#05060c] transition-opacity duration-500"
      style={{
        opacity: skipped ? 0 : phase >= 6 ? 0 : 1,
        pointerEvents: phase >= 6 || skipped ? "none" : "auto",
      }}
    >
      {/* drifting particle field, converging toward center as phase advances, hidden once flying starts */}
      <div
        className="pointer-events-none absolute inset-0 overflow-hidden transition-opacity duration-500"
        style={{ opacity: flying ? 0 : 1 }}
      >
        {Array.from({ length: SECURITY_ICONS.length }).map((_, i) => {
          const Icon = SECURITY_ICONS[i];
          const angle = (i / SECURITY_ICONS.length) * Math.PI * 2;
          const dist = 34;
          const startX = 50 + Math.cos(angle) * dist;
          const startY = 50 + Math.sin(angle) * dist;
          const delay = i * 55;
          return (
            <div
              key={i}
              className="absolute flex h-6 w-6 items-center justify-center text-indigo-300"
              style={{
                left: phase >= 2 ? "50%" : `${startX}%`,
                top: phase >= 2 ? "50%" : `${startY}%`,
                transform: "translate(-50%, -50%)",
                opacity: phase >= 1 && phase < 5 ? (phase >= 2 ? 0 : 0.9) : 0,
                filter: "drop-shadow(0 0 6px rgba(129,140,248,0.75))",
                transition: `left 1.05s cubic-bezier(.16,1,.3,1) ${delay}ms, top 1.05s cubic-bezier(.16,1,.3,1) ${delay}ms, opacity 0.7s ease ${delay + 350}ms`,
              }}
            >
              <Icon className="h-full w-full" strokeWidth={1.75} />
            </div>
          );
        })}
        <div
          className="absolute left-1/2 top-1/2 h-[420px] w-[420px] -translate-x-1/2 -translate-y-1/2 rounded-full bg-indigo-600/10 blur-[100px] transition-opacity duration-1000"
          style={{ opacity: phase >= 1 ? 1 : 0 }}
        />
      </div>

      {/* Wordmark + tagline, centered, fade out as the shield flies away */}
      <div
        className="absolute left-1/2 top-1/2 flex -translate-x-1/2 flex-col items-center transition-opacity duration-500"
        style={{ opacity: flying ? 0 : 1 }}
      >
        <div className="mb-7 h-24 w-24" /> {/* spacer matching the flying shield's original size */}
        <div className="flex overflow-hidden text-4xl font-semibold tracking-tight text-white sm:text-5xl">
          {wordmark.split("").map((ch, i) => (
            <span
              key={i}
              className={ch === "X" ? "text-indigo-400" : ""}
              style={{
                opacity: phase >= 3 ? 1 : 0,
                transform: phase >= 3 ? "translateY(0)" : "translateY(16px)",
                transition: `opacity 0.5s ease ${i * 45}ms, transform 0.5s cubic-bezier(.16,1,.3,1) ${i * 45}ms`,
              }}
            >
              {ch}
            </span>
          ))}
        </div>
        <p
          className="mt-4 text-sm tracking-[0.2em] text-white/50 transition-all duration-700"
          style={{ opacity: phase >= 4 ? 1 : 0, transform: phase >= 4 ? "translateY(0)" : "translateY(8px)" }}
        >
          AI SECURITY. REAL-TIME. EFFORTLESS.
        </p>
      </div>

      {/* The shield itself — a single element that flies from center to the header's logo slot */}
      <div className="absolute" style={shieldStyle}>
        <svg viewBox="0 0 100 100" className="h-full w-full">
          <defs>
            <linearGradient id="introShieldGrad" x1="0" y1="0" x2="1" y2="1">
              <stop offset="0%" stopColor="#818cf8" />
              <stop offset="100%" stopColor="#c084fc" />
            </linearGradient>
          </defs>
          <path
            d="M50 6 L88 20 V48 C88 72 72 88 50 96 C28 88 12 72 12 48 V20 Z"
            fill="none"
            stroke="url(#introShieldGrad)"
            strokeWidth="3"
            strokeLinecap="round"
            strokeLinejoin="round"
            pathLength="1"
            style={{
              strokeDasharray: 1,
              strokeDashoffset: phase >= 1 ? 0 : 1,
              transition: "stroke-dashoffset 1.15s cubic-bezier(.65,0,.35,1) 0.1s",
              filter: phase >= 2 && !flying ? "drop-shadow(0 0 14px rgba(129,140,248,0.85))" : "drop-shadow(0 0 6px rgba(129,140,248,0.6))",
            }}
          />
          <path
            d="M50 6 L88 20 V48 C88 72 72 88 50 96 C28 88 12 72 12 48 V20 Z"
            fill="rgba(129,140,248,0.06)"
            stroke="none"
            style={{ opacity: phase >= 2 ? 1 : 0, transition: "opacity 0.8s ease 0.2s" }}
          />
          {/* the "X" — drawn stroke by stroke once the shield has filled in */}
          <path
            d="M38 38 L62 62"
            fill="none"
            stroke="url(#introShieldGrad)"
            strokeWidth="4"
            strokeLinecap="round"
            pathLength="1"
            style={{
              strokeDasharray: 1,
              strokeDashoffset: phase >= 2 ? 0 : 1,
              transition: "stroke-dashoffset 0.5s cubic-bezier(.65,0,.35,1) 0.35s",
              filter: "drop-shadow(0 0 6px rgba(129,140,248,0.7))",
            }}
          />
          <path
            d="M62 38 L38 62"
            fill="none"
            stroke="url(#introShieldGrad)"
            strokeWidth="4"
            strokeLinecap="round"
            pathLength="1"
            style={{
              strokeDasharray: 1,
              strokeDashoffset: phase >= 2 ? 0 : 1,
              transition: "stroke-dashoffset 0.5s cubic-bezier(.65,0,.35,1) 0.55s",
              filter: "drop-shadow(0 0 6px rgba(129,140,248,0.7))",
            }}
          />
        </svg>
      </div>

      {!flying && (
        <button
          onClick={handleSkip}
          className="absolute bottom-8 right-8 text-xs font-medium text-white/40 transition hover:text-white/60"
        >
          Skip
        </button>
      )}

      <style>{`
        @keyframes sxPulseRing {
          0% { box-shadow: 0 0 0 0 rgba(129,140,248,0.55); }
          100% { box-shadow: 0 0 0 46px rgba(129,140,248,0); }
        }
      `}</style>
    </div>
  );
}
