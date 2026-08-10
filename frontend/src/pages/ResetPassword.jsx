import React, { useState } from "react";
import { Link, useNavigate, useSearchParams } from "react-router-dom";
import GlassCard from "../components/ui/GlassCard.jsx";
import { useAuth } from "../lib/AuthContext.jsx";
import { ArrowRight, Lock, AlertCircle, CheckCircle2 } from "lucide-react";

export default function ResetPassword() {
  const [password, setPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [formError, setFormError] = useState(null);
  const [done, setDone] = useState(false);

  const { resetPassword } = useAuth();
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const token = searchParams.get("token") || "demo_token";

  async function handleSubmit(e) {
    e.preventDefault();
    setFormError(null);
    if (password !== confirmPassword) {
      setFormError("Passwords do not match");
      return;
    }
    setSubmitting(true);
    const result = await resetPassword(token, password);
    setSubmitting(false);
    if (result.ok) {
      setDone(true);
      setTimeout(() => navigate("/login", { replace: true }), 1800);
    } else {
      setFormError(result.message);
    }
  }

  if (done) {
    return (
      <GlassCard className="p-8 text-center">
        <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-emerald-500/10">
          <CheckCircle2 className="h-6 w-6 text-emerald-400" />
        </div>
        <h1 className="text-xl font-semibold text-white">Password updated</h1>
        <p className="mt-2 text-sm text-white/55">Redirecting you to sign in...</p>
      </GlassCard>
    );
  }

  return (
    <GlassCard className="p-8">
      <h1 className="text-xl font-semibold text-white">Set a new password</h1>
      <p className="mt-1.5 text-sm text-white/55">Choose something you haven't used before.</p>

      {formError && (
        <div className="mt-5 flex items-center gap-2 rounded-lg border border-rose-500/20 bg-rose-500/[0.08] px-3.5 py-2.5 text-xs text-rose-300">
          <AlertCircle className="h-3.5 w-3.5 shrink-0" />
          {formError}
        </div>
      )}

      <form onSubmit={handleSubmit} className="mt-6 space-y-4">
        <div>
          <label className="mb-1.5 block text-xs font-medium text-white/65">New password</label>
          <div className="flex items-center gap-2 rounded-lg border border-white/[0.14] bg-white/[0.05] px-3.5 py-2.5 focus-within:border-indigo-400/50">
            <Lock className="h-4 w-4 text-white/45" />
            <input
              type="password"
              required
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              placeholder="At least 8 characters"
              className="w-full bg-transparent text-sm text-white placeholder:text-white/40 focus:outline-none"
            />
          </div>
        </div>

        <div>
          <label className="mb-1.5 block text-xs font-medium text-white/65">Confirm password</label>
          <div className="flex items-center gap-2 rounded-lg border border-white/[0.14] bg-white/[0.05] px-3.5 py-2.5 focus-within:border-indigo-400/50">
            <Lock className="h-4 w-4 text-white/45" />
            <input
              type="password"
              required
              value={confirmPassword}
              onChange={(e) => setConfirmPassword(e.target.value)}
              placeholder="Repeat password"
              className="w-full bg-transparent text-sm text-white placeholder:text-white/40 focus:outline-none"
            />
          </div>
        </div>

        <button
          type="submit"
          disabled={submitting}
          className="group flex w-full items-center justify-center gap-2 rounded-lg bg-white py-3 text-sm font-semibold text-[#07080f] shadow-[0_0_30px_-8px_rgba(129,140,248,0.6)] transition hover:shadow-[0_0_40px_-4px_rgba(129,140,248,0.8)] disabled:opacity-60"
        >
          {submitting ? "Updating..." : "Update password"}
          {!submitting && <ArrowRight className="h-4 w-4 transition group-hover:translate-x-0.5" />}
        </button>
      </form>

      <p className="mt-6 text-center text-xs text-white/45">
        <Link to="/login" className="font-medium text-indigo-400 hover:text-indigo-300">
          Back to sign in
        </Link>
      </p>
    </GlassCard>
  );
}
