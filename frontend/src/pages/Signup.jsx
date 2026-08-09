import React, { useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import GlassCard from "../components/ui/GlassCard.jsx";
import { useAuth } from "../lib/AuthContext.jsx";
import {
  ArrowRight, ArrowLeft, Building2, User, Mail, Lock, AlertCircle,
  Check, Zap, Building, Rocket, CheckCircle2,
} from "lucide-react";

const STEPS = ["Account", "Security", "Plan"];

const PLANS = [
  { id: "free_trial", name: "Free Trial", price: "$0", desc: "14 days, up to 3 agents", icon: Zap },
  { id: "pro", name: "Pro", price: "$49/mo", desc: "Up to 20 agents, priority alerts", icon: Building },
  { id: "enterprise", name: "Enterprise", price: "Custom", desc: "Unlimited agents, SSO, SLA", icon: Rocket },
];

function scorePassword(pw) {
  let score = 0;
  if (pw.length >= 8) score++;
  if (pw.length >= 12) score++;
  if (/[A-Z]/.test(pw) && /[a-z]/.test(pw)) score++;
  if (/\d/.test(pw)) score++;
  if (/[^A-Za-z0-9]/.test(pw)) score++;
  return Math.min(score, 4);
}

const STRENGTH_META = [
  { label: "Very weak", color: "bg-rose-500" },
  { label: "Weak", color: "bg-rose-400" },
  { label: "Okay", color: "bg-amber-400" },
  { label: "Good", color: "bg-emerald-400" },
  { label: "Strong", color: "bg-emerald-400" },
];

export default function Signup() {
  const [step, setStep] = useState(0);
  const [companyName, setCompanyName] = useState("");
  const [adminName, setAdminName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");
  const [agreedToTerms, setAgreedToTerms] = useState(false);
  const [plan, setPlan] = useState("free_trial");
  const [submitting, setSubmitting] = useState(false);
  const [formError, setFormError] = useState(null);
  const [registered, setRegistered] = useState(false);
  const [registeredMessage, setRegisteredMessage] = useState(null);

  const { signup } = useAuth();
  const navigate = useNavigate();

  const strength = scorePassword(password);
  const strengthMeta = STRENGTH_META[strength];

  function validateStep(current) {
    setFormError(null);
    if (current === 0) {
      if (!companyName.trim() || !adminName.trim() || !email.trim()) {
        setFormError("Please fill in every field before continuing");
        return false;
      }
      if (!/^\S+@\S+\.\S+$/.test(email)) {
        setFormError("Enter a valid email address");
        return false;
      }
      return true;
    }
    if (current === 1) {
      if (password.length < 8) {
        setFormError("Password must be at least 8 characters");
        return false;
      }
      if (password !== confirmPassword) {
        setFormError("Passwords do not match");
        return false;
      }
      if (!agreedToTerms) {
        setFormError("You need to agree to the Terms & Conditions to continue");
        return false;
      }
      return true;
    }
    return true;
  }

  function handleNext() {
    if (validateStep(step)) setStep((s) => Math.min(s + 1, STEPS.length - 1));
  }

  function handleBack() {
    setFormError(null);
    setStep((s) => Math.max(s - 1, 0));
  }

  async function handleSubmit(e) {
    e.preventDefault();
    if (!validateStep(step)) return;
    setSubmitting(true);
    const result = await signup({
      organization_name: companyName,
      full_name: adminName,
      email,
      password,
      password_confirmation: confirmPassword,
    });
    setSubmitting(false);
    if (result.ok) {
      // Registration never issues a token — the account needs its email
      // verified before it can sign in (LoginUserAction rejects
      // unverified users), so there is no session to land in /dashboard
      // with yet.
      setRegisteredMessage(result.message || "Check your email to verify your account.");
      setRegistered(true);
    } else {
      setFormError(result.message || "Something went wrong");
    }
  }

  if (registered) {
    return (
      <GlassCard className="p-8 text-center">
        <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-emerald-500/10">
          <CheckCircle2 className="h-6 w-6 text-emerald-400" />
        </div>
        <h1 className="text-xl font-semibold text-white">Check your inbox</h1>
        <p className="mt-2 text-sm text-white/40">{registeredMessage}</p>
        <p className="mt-1 text-sm text-white/40">
          Once verified, sign in with <span className="text-white/70">{email}</span> to get started.
        </p>
        <button
          onClick={() => navigate("/login", { replace: true, state: { justRegisteredMessage: registeredMessage } })}
          className="mt-6 inline-flex items-center gap-1.5 text-sm font-medium text-indigo-400 hover:text-indigo-300"
        >
          Go to sign in <ArrowRight className="h-3.5 w-3.5" />
        </button>
      </GlassCard>
    );
  }

  return (
    <GlassCard className="p-8">
      <h1 className="text-xl font-semibold text-white">Create your workspace</h1>
      <p className="mt-1.5 text-sm text-white/40">Start securing your agents in minutes.</p>

      {/* Step indicator */}
      <div className="mt-6 flex items-center gap-2">
        {STEPS.map((label, i) => (
          <React.Fragment key={label}>
            <div className="flex items-center gap-2">
              <div
                className={`flex h-6 w-6 items-center justify-center rounded-full text-[11px] font-semibold transition ${
                  i < step
                    ? "bg-indigo-500 text-white"
                    : i === step
                    ? "bg-white text-[#07080f]"
                    : "bg-white/[0.08] text-white/30"
                }`}
              >
                {i < step ? <Check className="h-3 w-3" /> : i + 1}
              </div>
              <span className={`text-xs ${i === step ? "text-white/70" : "text-white/30"}`}>{label}</span>
            </div>
            {i < STEPS.length - 1 && <div className={`h-px flex-1 ${i < step ? "bg-indigo-500" : "bg-white/[0.08]"}`} />}
          </React.Fragment>
        ))}
      </div>

      {formError && (
        <div className="mt-5 flex items-center gap-2 rounded-lg border border-rose-500/20 bg-rose-500/[0.08] px-3.5 py-2.5 text-xs text-rose-300">
          <AlertCircle className="h-3.5 w-3.5 shrink-0" />
          {formError}
        </div>
      )}

      <form onSubmit={step === STEPS.length - 1 ? handleSubmit : (e) => { e.preventDefault(); handleNext(); }} className="mt-6 space-y-4">
        {/* Step 1 — Account */}
        {step === 0 && (
          <>
            <div>
              <label className="mb-1.5 block text-xs font-medium text-white/50">Company name</label>
              <div className="flex items-center gap-2 rounded-lg border border-white/[0.1] bg-white/[0.03] px-3.5 py-2.5 focus-within:border-indigo-400/50">
                <Building2 className="h-4 w-4 text-white/30" />
                <input
                  value={companyName}
                  onChange={(e) => setCompanyName(e.target.value)}
                  placeholder="FutureBank"
                  className="w-full bg-transparent text-sm text-white placeholder:text-white/25 focus:outline-none"
                />
              </div>
            </div>
            <div>
              <label className="mb-1.5 block text-xs font-medium text-white/50">Your name</label>
              <div className="flex items-center gap-2 rounded-lg border border-white/[0.1] bg-white/[0.03] px-3.5 py-2.5 focus-within:border-indigo-400/50">
                <User className="h-4 w-4 text-white/30" />
                <input
                  value={adminName}
                  onChange={(e) => setAdminName(e.target.value)}
                  placeholder="Ahmed"
                  className="w-full bg-transparent text-sm text-white placeholder:text-white/25 focus:outline-none"
                />
              </div>
            </div>
            <div>
              <label className="mb-1.5 block text-xs font-medium text-white/50">Work email</label>
              <div className="flex items-center gap-2 rounded-lg border border-white/[0.1] bg-white/[0.03] px-3.5 py-2.5 focus-within:border-indigo-400/50">
                <Mail className="h-4 w-4 text-white/30" />
                <input
                  type="email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  placeholder="ahmed@futurebank.com"
                  className="w-full bg-transparent text-sm text-white placeholder:text-white/25 focus:outline-none"
                />
              </div>
            </div>
          </>
        )}

        {/* Step 2 — Security */}
        {step === 1 && (
          <>
            <div>
              <label className="mb-1.5 block text-xs font-medium text-white/50">Password</label>
              <div className="flex items-center gap-2 rounded-lg border border-white/[0.1] bg-white/[0.03] px-3.5 py-2.5 focus-within:border-indigo-400/50">
                <Lock className="h-4 w-4 text-white/30" />
                <input
                  type="password"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  placeholder="At least 8 characters"
                  className="w-full bg-transparent text-sm text-white placeholder:text-white/25 focus:outline-none"
                />
              </div>
              {password && (
                <div className="mt-2">
                  <div className="flex gap-1">
                    {[0, 1, 2, 3].map((i) => (
                      <div
                        key={i}
                        className={`h-1 flex-1 rounded-full transition-colors ${i <= strength - 1 ? strengthMeta.color : "bg-white/[0.08]"}`}
                      />
                    ))}
                  </div>
                  <div className="mt-1 text-[11px] text-white/35">{strengthMeta.label}</div>
                </div>
              )}
            </div>
            <div>
              <label className="mb-1.5 block text-xs font-medium text-white/50">Confirm password</label>
              <div className="flex items-center gap-2 rounded-lg border border-white/[0.1] bg-white/[0.03] px-3.5 py-2.5 focus-within:border-indigo-400/50">
                <Lock className="h-4 w-4 text-white/30" />
                <input
                  type="password"
                  value={confirmPassword}
                  onChange={(e) => setConfirmPassword(e.target.value)}
                  placeholder="Repeat password"
                  className="w-full bg-transparent text-sm text-white placeholder:text-white/25 focus:outline-none"
                />
              </div>
            </div>
            <label className="flex cursor-pointer items-start gap-2.5 pt-1">
              <input
                type="checkbox"
                checked={agreedToTerms}
                onChange={(e) => setAgreedToTerms(e.target.checked)}
                className="mt-0.5 h-4 w-4 accent-indigo-500"
              />
              <span className="text-xs leading-relaxed text-white/45">
                I agree to the{" "}
                <a href="#" className="text-indigo-400 hover:text-indigo-300">Terms & Conditions</a> and{" "}
                <a href="#" className="text-indigo-400 hover:text-indigo-300">Privacy Policy</a>.
              </span>
            </label>
          </>
        )}

        {/* Step 3 — Plan */}
        {step === 2 && (
          <div className="space-y-2.5">
            {PLANS.map((p) => (
              <label
                key={p.id}
                className={`flex cursor-pointer items-center gap-3 rounded-lg border px-3.5 py-3 transition ${
                  plan === p.id ? "border-indigo-400/50 bg-indigo-500/[0.08]" : "border-white/[0.1] bg-white/[0.02] hover:bg-white/[0.04]"
                }`}
              >
                <input
                  type="radio"
                  name="plan"
                  value={p.id}
                  checked={plan === p.id}
                  onChange={() => setPlan(p.id)}
                  className="h-4 w-4 accent-indigo-500"
                />
                <p.icon className="h-4 w-4 text-indigo-400" />
                <div className="flex-1">
                  <div className="text-sm font-medium text-white">{p.name}</div>
                  <div className="text-xs text-white/35">{p.desc}</div>
                </div>
                <div className="text-sm font-semibold text-white/70">{p.price}</div>
              </label>
            ))}
          </div>
        )}

        <div className="flex items-center gap-3 pt-1">
          {step > 0 && (
            <button
              type="button"
              onClick={handleBack}
              className="flex items-center gap-1.5 rounded-lg border border-white/[0.1] px-4 py-3 text-sm font-medium text-white/70 transition hover:bg-white/[0.05]"
            >
              <ArrowLeft className="h-4 w-4" /> Back
            </button>
          )}
          <button
            type="submit"
            disabled={submitting}
            className="group flex flex-1 items-center justify-center gap-2 rounded-lg bg-white py-3 text-sm font-semibold text-[#07080f] shadow-[0_0_30px_-8px_rgba(129,140,248,0.6)] transition hover:shadow-[0_0_40px_-4px_rgba(129,140,248,0.8)] disabled:opacity-60"
          >
            {step === STEPS.length - 1
              ? submitting ? "Creating workspace..." : "Create Workspace"
              : "Continue"}
            {!submitting && <ArrowRight className="h-4 w-4 transition group-hover:translate-x-0.5" />}
          </button>
        </div>
      </form>

      <p className="mt-6 text-center text-xs text-white/30">
        Already have a workspace?{" "}
        <Link to="/login" className="font-medium text-indigo-400 hover:text-indigo-300">
          Sign in
        </Link>
      </p>
    </GlassCard>
  );
}
