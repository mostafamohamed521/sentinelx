import React, { useState } from "react";
import { Link } from "react-router-dom";
import GlassCard from "../components/ui/GlassCard.jsx";
import { useAuth } from "../lib/AuthContext.jsx";
import { ArrowRight, Mail, CheckCircle2 } from "lucide-react";

export default function ForgotPassword() {
  const [email, setEmail] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [sent, setSent] = useState(false);
  const { forgotPassword } = useAuth();

  async function handleSubmit(e) {
    e.preventDefault();
    setSubmitting(true);
    const result = await forgotPassword(email);
    setSubmitting(false);
    if (result.ok) setSent(true);
  }

  if (sent) {
    return (
      <GlassCard className="p-8 text-center">
        <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-emerald-500/10">
          <CheckCircle2 className="h-6 w-6 text-emerald-400" />
        </div>
        <h1 className="text-xl font-semibold text-white">Check your inbox</h1>
        <p className="mt-2 text-sm text-white/55">
          If an account exists for <span className="text-white/70">{email}</span>, a reset link is on its way.
        </p>
        <Link to="/login" className="mt-6 inline-block text-sm font-medium text-indigo-400 hover:text-indigo-300">
          Back to sign in
        </Link>
      </GlassCard>
    );
  }

  return (
    <GlassCard className="p-8">
      <h1 className="text-xl font-semibold text-white">Reset your password</h1>
      <p className="mt-1.5 text-sm text-white/55">We'll email you a link to get back in.</p>

      <form onSubmit={handleSubmit} className="mt-6 space-y-4">
        <div>
          <label className="mb-1.5 block text-xs font-medium text-white/65">Email</label>
          <div className="flex items-center gap-2 rounded-lg border border-white/[0.14] bg-white/[0.05] px-3.5 py-2.5 focus-within:border-indigo-400/50">
            <Mail className="h-4 w-4 text-white/45" />
            <input
              type="email"
              required
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="ahmed@company.com"
              className="w-full bg-transparent text-sm text-white placeholder:text-white/40 focus:outline-none"
            />
          </div>
        </div>

        <button
          type="submit"
          disabled={submitting}
          className="group flex w-full items-center justify-center gap-2 rounded-lg bg-white py-3 text-sm font-semibold text-[#07080f] shadow-[0_0_30px_-8px_rgba(129,140,248,0.6)] transition hover:shadow-[0_0_40px_-4px_rgba(129,140,248,0.8)] disabled:opacity-60"
        >
          {submitting ? "Sending..." : "Send reset link"}
          {!submitting && <ArrowRight className="h-4 w-4 transition group-hover:translate-x-0.5" />}
        </button>
      </form>

      <p className="mt-6 text-center text-xs text-white/45">
        Remembered it?{" "}
        <Link to="/login" className="font-medium text-indigo-400 hover:text-indigo-300">
          Sign in
        </Link>
      </p>
    </GlassCard>
  );
}
