import React, { useState } from "react";
import { Link, useNavigate, useLocation } from "react-router-dom";
import GlassCard from "../components/ui/GlassCard.jsx";
import { useAuth } from "../lib/AuthContext.jsx";
import { MOCK_MODE } from "../lib/apiClient.js";
import { ArrowRight, Mail, Lock, AlertCircle, CheckCircle2 } from "lucide-react";

export default function Login() {
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [formError, setFormError] = useState(null);

  const { login } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const from = location.state?.from?.pathname || "/dashboard";
  // Signup.jsx redirects here after registration (no token is issued until
  // the account's email is verified), carrying a one-time success message.
  const justRegisteredMessage = location.state?.justRegisteredMessage;

  async function handleSubmit(e) {
    e.preventDefault();
    setFormError(null);
    setSubmitting(true);
    const result = await login(email, password);
    setSubmitting(false);
    if (result.ok) {
      navigate(from, { replace: true });
    } else {
      setFormError(result.message || "Something went wrong");
    }
  }

  return (
    <GlassCard className="p-8">
      <h1 className="text-xl font-semibold text-white">Welcome back</h1>
      <p className="mt-1.5 text-sm text-white/55">Sign in to your SentinelX workspace.</p>

      {justRegisteredMessage && (
        <div className="mt-5 flex items-center gap-2 rounded-lg border border-emerald-500/20 bg-emerald-500/[0.08] px-3.5 py-2.5 text-xs text-emerald-300">
          <CheckCircle2 className="h-3.5 w-3.5 shrink-0" />
          {justRegisteredMessage}
        </div>
      )}

      {formError && (
        <div className="mt-5 flex items-center gap-2 rounded-lg border border-rose-500/20 bg-rose-500/[0.08] px-3.5 py-2.5 text-xs text-rose-300">
          <AlertCircle className="h-3.5 w-3.5 shrink-0" />
          {formError}
        </div>
      )}

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

        <div>
          <div className="mb-1.5 flex items-center justify-between">
            <label className="block text-xs font-medium text-white/65">Password</label>
            <Link to="/forgot-password" className="text-xs font-medium text-indigo-400 hover:text-indigo-300">
              Forgot password?
            </Link>
          </div>
          <div className="flex items-center gap-2 rounded-lg border border-white/[0.14] bg-white/[0.05] px-3.5 py-2.5 focus-within:border-indigo-400/50">
            <Lock className="h-4 w-4 text-white/45" />
            <input
              type="password"
              required
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              placeholder="••••••••"
              className="w-full bg-transparent text-sm text-white placeholder:text-white/40 focus:outline-none"
            />
          </div>
        </div>

        <button
          type="submit"
          disabled={submitting}
          className="group flex w-full items-center justify-center gap-2 rounded-lg bg-white py-3 text-sm font-semibold text-[#07080f] shadow-[0_0_30px_-8px_rgba(129,140,248,0.6)] transition hover:shadow-[0_0_40px_-4px_rgba(129,140,248,0.8)] disabled:opacity-60"
        >
          {submitting ? "Signing in..." : "Sign in"}
          {!submitting && <ArrowRight className="h-4 w-4 transition group-hover:translate-x-0.5" />}
        </button>
      </form>

      <p className="mt-6 text-center text-xs text-white/45">
        Don't have a workspace?{" "}
        <Link to="/signup" className="font-medium text-indigo-400 hover:text-indigo-300">
          Create one
        </Link>
      </p>

      {MOCK_MODE && (
        <p className="mt-3 text-center text-[11px] text-white/35">
          Demo: any email + a password of 4+ characters will sign you in.
        </p>
      )}
    </GlassCard>
  );
}
