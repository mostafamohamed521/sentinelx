import React, { useEffect, useRef, useState } from "react";
import { Link } from "react-router-dom";
import {
  Shield, Zap, Activity, Eye, Lock, Globe, ArrowRight,
  AlertTriangle, TrendingUp, TrendingDown, CheckCircle2,
  Cpu, FileText, Network, Database, Terminal
} from "lucide-react";
import Logo from "../components/ui/Logo.jsx";
import AmbientField from "../components/ui/AmbientField.jsx";
import GlassCard from "../components/ui/GlassCard.jsx";
import Reveal from "../components/ui/Reveal.jsx";
import { useCountUp, useOnScreen } from "../hooks/useOnScreen.js";

function Nav() {
  const [scrolled, setScrolled] = useState(false);
  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 12);
    window.addEventListener("scroll", onScroll);
    return () => window.removeEventListener("scroll", onScroll);
  }, []);
  return (
    <nav className={`fixed top-0 z-50 w-full transition-all duration-500 ${scrolled ? "bg-[#07080f]/80 backdrop-blur-xl border-b border-white/[0.06]" : "bg-transparent"}`}>
      <div className="mx-auto flex max-w-7xl items-center justify-between px-6 py-4">
        <div className="flex items-center gap-2.5">
          <Logo size={32} />
          <span className="text-[15px] font-semibold tracking-tight text-white">Sentinel<span className="text-indigo-400">X</span></span>
        </div>
        <div className="hidden items-center gap-8 text-sm text-white/60 md:flex">
          <a href="#platform" className="transition hover:text-white">Platform</a>
          <a href="#detection" className="transition hover:text-white">Detection</a>
          <a href="#insights" className="transition hover:text-white">Insights</a>
          <a href="#trust" className="transition hover:text-white">Trust</a>
        </div>
        <div className="flex items-center gap-3">
          <Link to="/login" className="text-sm font-medium text-white/60 transition hover:text-white">
            Sign in
          </Link>
          <Link to="/signup" className="group flex items-center gap-1.5 rounded-full bg-white/[0.06] px-4 py-2 text-sm font-medium text-white ring-1 ring-white/[0.1] transition hover:bg-white/[0.1]">
            Get started
            <ArrowRight className="h-3.5 w-3.5 transition group-hover:translate-x-0.5" />
          </Link>
        </div>
      </div>
    </nav>
  );
}

function Hero() {
  const [step, setStep] = useState(0);
  useEffect(() => {
    [300, 900, 1500, 2100].forEach((t, i) => setTimeout(() => setStep(i + 1), t));
  }, []);
  const liveEvents = [
    { t: "10:21:31", type: "API Request", detail: "OpenAI /v1/chat/completions", risk: "low" },
    { t: "10:21:32", type: "File Access", detail: "/data/financial_report.xlsx", risk: "low" },
    { t: "10:21:33", type: "Tool Use", detail: "code_interpreter", risk: "low" },
    { t: "10:21:34", type: "API Response", detail: "200 OK", risk: "low" },
    { t: "10:21:35", type: "DB Query", detail: "SELECT * FROM users", risk: "med" },
    { t: "10:21:36", type: "External Request", detail: "api.stripe.com/v1/charges", risk: "med" },
    { t: "10:21:37", type: "File Write", detail: "/tmp/payload.bin", risk: "high" },
  ];
  return (
    <header className="relative overflow-hidden bg-[#07080f] pb-28 pt-40">
      <AmbientField />
      <div className="relative mx-auto grid max-w-7xl grid-cols-1 gap-16 px-6 lg:grid-cols-[1.05fr_0.95fr] lg:items-center">
        <div>
          <div className="mb-6 inline-flex items-center gap-2 rounded-full border border-indigo-400/20 bg-indigo-500/[0.07] px-3.5 py-1.5 text-xs font-medium text-indigo-300 transition-all duration-700" style={{ opacity: step >= 1 ? 1 : 0, transform: step >= 1 ? "translateY(0)" : "translateY(10px)" }}>
            <span className="relative flex h-1.5 w-1.5">
              <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-indigo-400 opacity-75" />
              <span className="relative inline-flex h-1.5 w-1.5 rounded-full bg-indigo-400" />
            </span>
            Now monitoring live agent behavior
          </div>
          <h1 className="text-[2.75rem] font-semibold leading-[1.05] tracking-tight text-white transition-all duration-700 sm:text-6xl" style={{ opacity: step >= 1 ? 1 : 0, transform: step >= 1 ? "translateY(0)" : "translateY(16px)" }}>
            Your AI agents move fast.
            <br />
            <span className="bg-gradient-to-r from-indigo-300 via-violet-300 to-indigo-200 bg-clip-text text-transparent">Someone should watch them.</span>
          </h1>
          <p className="mt-6 max-w-lg text-lg leading-relaxed text-white/65 transition-all duration-700" style={{ opacity: step >= 2 ? 1 : 0, transform: step >= 2 ? "translateY(0)" : "translateY(14px)" }}>
            SentinelX watches every action your AI agents take — every API call, every file, every tool — and tells you the moment something looks wrong.
          </p>
          <div className="mt-9 flex flex-wrap items-center gap-4 transition-all duration-700" style={{ opacity: step >= 3 ? 1 : 0, transform: step >= 3 ? "translateY(0)" : "translateY(14px)" }}>
            <Link to="/login" className="group relative flex items-center gap-2 overflow-hidden rounded-xl bg-white px-6 py-3.5 text-sm font-semibold text-[#07080f] shadow-[0_0_40px_-8px_rgba(129,140,248,0.6)] transition hover:shadow-[0_0_50px_-4px_rgba(129,140,248,0.8)]">
              Create your workspace
              <ArrowRight className="h-4 w-4 transition group-hover:translate-x-1" />
            </Link>
            <a href="#detection" className="rounded-xl border border-white/[0.1] px-6 py-3.5 text-sm font-medium text-white/80 transition hover:bg-white/[0.05] hover:text-white">
              Watch it detect a threat →
            </a>
          </div>
          <div className="mt-14 flex flex-wrap items-center gap-x-10 gap-y-4 border-t border-white/[0.06] pt-8 text-xs text-white/50 transition-all duration-700" style={{ opacity: step >= 4 ? 1 : 0 }}>
            <span className="tracking-wide">CONNECTS WITH</span>
            <span className="font-medium text-white/55">OpenAI Agents</span>
            <span className="font-medium text-white/55">LangChain</span>
            <span className="font-medium text-white/55">CrewAI</span>
            <span className="font-medium text-white/55">Custom Agents</span>
          </div>
        </div>
        <Reveal delay={200}>
          <GlassCard className="relative overflow-hidden">
            <div className="flex items-center justify-between border-b border-white/[0.06] px-5 py-3.5">
              <div className="flex items-center gap-2 text-sm font-medium text-white/80">
                <Shield className="h-4 w-4 text-indigo-400" /> SentinelX — Live
              </div>
              <div className="flex gap-3 text-[11px] text-white/50">
                <span className="rounded-md bg-white/[0.06] px-2 py-1">Live</span>
                <span className="px-2 py-1">Events</span>
                <span className="px-2 py-1">Logs</span>
              </div>
            </div>
            <div className="max-h-[340px] space-y-0.5 overflow-hidden p-3 font-mono text-[12px]">
              {liveEvents.map((e, i) => (
                <div key={i} className="flex items-center gap-3 rounded-lg px-2.5 py-2 transition-all duration-500 hover:bg-white/[0.04]" style={{ animation: `fadeSlideIn 0.5s ease-out ${0.15 * i + 0.4}s both` }}>
                  <span className="text-white/45">{e.t}</span>
                  <span className={`h-1.5 w-1.5 shrink-0 rounded-full ${e.risk === "high" ? "bg-rose-400" : e.risk === "med" ? "bg-amber-400" : "bg-emerald-400"}`} />
                  <span className="w-28 shrink-0 text-white/70">{e.type}</span>
                  <span className="truncate text-white/55">{e.detail}</span>
                </div>
              ))}
            </div>
            <div className="border-t border-white/[0.1] bg-white/[0.04] px-5 py-3 text-[11px] text-white/55">
              All signals flow into one intelligent brain — behavior analysis, threat detection, risk scoring.
            </div>
          </GlassCard>
        </Reveal>
      </div>
    </header>
  );
}

function Onboarding() {
  return (
    <section id="platform" className="relative bg-[#07080f] py-24">
      <div className="mx-auto max-w-7xl px-6">
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
          <Reveal><GlassCard className="flex h-full flex-col justify-center gap-4 p-8">
            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-500/10"><Cpu className="h-5 w-5 text-indigo-400" /></div>
            <h3 className="text-xl font-semibold text-white">Create your workspace</h3>
            <p className="text-sm leading-relaxed text-white/60">Start securing your agents in minutes — no infrastructure to manage.</p>
          </GlassCard></Reveal>
          <Reveal delay={120}><GlassCard className="flex h-full flex-col justify-center gap-4 p-8">
            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-violet-500/10"><Network className="h-5 w-5 text-violet-400" /></div>
            <h3 className="text-xl font-semibold text-white">Connect your AI agents</h3>
            <div className="mt-2 space-y-2">
              {["OpenAI", "LangChain", "CrewAI", "Custom Agent"].map((name) => (
                <div key={name} className="flex items-center justify-between rounded-lg border border-white/[0.08] bg-white/[0.03] px-3.5 py-2.5">
                  <span className="text-sm text-white/70">{name}</span>
                  <span className="flex items-center gap-1.5 text-[11px] text-emerald-400/80"><CheckCircle2 className="h-3 w-3" /> Connected</span>
                </div>
              ))}
            </div>
          </GlassCard></Reveal>
          <Reveal delay={240}><GlassCard className="relative flex h-full flex-col justify-center gap-4 overflow-hidden p-8">
            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-500/10"><Eye className="h-5 w-5 text-blue-400" /></div>
            <h3 className="text-xl font-semibold text-white">We start monitoring <span className="text-indigo-300">everything</span></h3>
            <div className="mt-2 grid grid-cols-3 gap-2 text-center">
              {[{ icon: Terminal, label: "Requests" }, { icon: Activity, label: "Responses" }, { icon: Zap, label: "Events" }, { icon: FileText, label: "Tools" }, { icon: Database, label: "Files" }, { icon: Globe, label: "Network" }].map(({ icon: Icon, label }) => (
                <div key={label} className="rounded-lg border border-white/[0.06] bg-white/[0.03] px-2 py-3">
                  <Icon className="mx-auto mb-1.5 h-4 w-4 text-white/65" />
                  <div className="text-[10px] text-white/50">{label}</div>
                </div>
              ))}
            </div>
          </GlassCard></Reveal>
        </div>
      </div>
    </section>
  );
}

function RealtimeShowcase() {
  const [ref, visible] = useOnScreen();
  const req = useCountUp(18400, 1600, visible);
  return (
    <section id="detection" className="relative bg-[#07080f] py-24" ref={ref}>
      <div className="mx-auto max-w-7xl px-6">
        <div className="grid grid-cols-1 items-center gap-14 lg:grid-cols-2">
          <Reveal>
            <p className="mb-3 text-sm font-medium text-indigo-400">Real-time</p>
            <h2 className="text-4xl font-semibold leading-tight tracking-tight text-white">Every request. Every decision. Every action.</h2>
            <p className="mt-5 max-w-md text-white/65">All signals flow into one intelligent brain — behavior analysis, threat detection, risk scoring, anomaly detection.</p>
          </Reveal>
          <Reveal delay={150}>
            <GlassCard className="p-5">
              <div className="mb-4 flex items-center justify-between text-sm font-medium text-white">
                <span className="flex items-center gap-2"><Shield className="h-4 w-4 text-indigo-400" /> Overview</span>
                <span className="text-[11px] font-normal text-white/45">Last 24h</span>
              </div>
              <div className="text-3xl font-semibold text-white">{(req / 1000).toFixed(1)}K</div>
              <div className="text-xs text-white/50">requests today</div>
            </GlassCard>
          </Reveal>
        </div>
      </div>
    </section>
  );
}

function Trust() {
  return (
    <section id="trust" className="relative overflow-hidden bg-[#07080f] py-28">
      <AmbientField />
      <div className="relative mx-auto max-w-4xl px-6 text-center">
        <Reveal>
          <p className="mb-3 text-sm font-medium text-indigo-400">You build AI. We protect it.</p>
          <h2 className="text-4xl font-semibold tracking-tight text-white sm:text-5xl">
            Together, <span className="bg-gradient-to-r from-indigo-300 to-violet-300 bg-clip-text text-transparent">we build trust.</span>
          </h2>
          <Link to="/login" className="group mx-auto mt-10 flex w-fit items-center gap-2 rounded-xl bg-white px-7 py-4 text-sm font-semibold text-[#07080f] shadow-[0_0_50px_-10px_rgba(129,140,248,0.7)] transition hover:shadow-[0_0_60px_-6px_rgba(129,140,248,0.9)]">
            Create your workspace
            <ArrowRight className="h-4 w-4 transition group-hover:translate-x-1" />
          </Link>
        </Reveal>
      </div>
    </section>
  );
}

function FooterStrip() {
  const items = [
    { icon: Zap, label: "Easy Onboarding" }, { icon: Network, label: "Connect Any Agent" },
    { icon: Eye, label: "Real-Time Monitoring" }, { icon: AlertTriangle, label: "Threat Detection" },
    { icon: Activity, label: "Deep Analytics" }, { icon: Lock, label: "Enterprise Ready" },
  ];
  return (
    <footer className="relative border-t border-white/[0.06] bg-[#07080f] py-14">
      <div className="relative mx-auto max-w-6xl px-6 text-center">
        <h3 className="text-2xl font-semibold text-white">
          AI moves fast. <span className="text-white/55">You stay</span>{" "}
          <span className="bg-gradient-to-r from-indigo-300 to-violet-300 bg-clip-text text-transparent">in control.</span>
        </h3>
        <div className="mt-10 flex flex-wrap items-center justify-center gap-x-10 gap-y-6">
          {items.map(({ icon: Icon, label }) => (
            <div key={label} className="flex items-center gap-2 text-xs text-white/50"><Icon className="h-3.5 w-3.5" /> {label}</div>
          ))}
        </div>
        <div className="mt-10 flex items-center justify-center gap-2 text-sm text-white/45">
          <Logo size={18} glow={false} /> SentinelX © 2026
        </div>
      </div>
    </footer>
  );
}

export default function Landing() {
  return (
    <div className="min-h-screen bg-[#07080f]">
      <Nav />
      <Hero />
      <Onboarding />
      <RealtimeShowcase />
      <Trust />
      <FooterStrip />
    </div>
  );
}
