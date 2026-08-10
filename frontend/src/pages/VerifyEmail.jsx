import React, { useEffect, useState } from "react";
import { Link, useParams, useSearchParams } from "react-router-dom";
import GlassCard from "../components/ui/GlassCard.jsx";
import { verifyEmail } from "../lib/api/auth.js";
import { CheckCircle2, AlertCircle, Loader2 } from "lucide-react";

// GET /v1/auth/verify-email/{id}/{hash}?signature=...&expires=... — a
// Laravel signed URL. The `signature`/`expires` query params must be
// forwarded to the Backend byte-for-byte from the link the user clicked in
// their email, since the `signed` middleware validates them against the
// full URL. This page exists purely to give that link a branded landing
// screen; it does not construct or modify the signature itself.
export default function VerifyEmail() {
  const { id, hash } = useParams();
  const [searchParams] = useSearchParams();
  const [status, setStatus] = useState("verifying"); // verifying | success | error
  const [message, setMessage] = useState(null);

  useEffect(() => {
    async function run() {
      try {
        const qs = searchParams.toString();
        const res = await verifyEmail(id, hash, qs ? `?${qs}` : "");
        setMessage(res.message);
        setStatus("success");
      } catch (e) {
        setMessage(e.message || "This verification link is invalid or has expired.");
        setStatus("error");
      }
    }
    run();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id, hash]);

  return (
    <GlassCard className="p-8 text-center">
      {status === "verifying" && (
        <>
          <Loader2 className="mx-auto mb-4 h-8 w-8 animate-spin text-indigo-400" />
          <h1 className="text-xl font-semibold text-white">Verifying your email...</h1>
        </>
      )}

      {status === "success" && (
        <>
          <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-emerald-500/10">
            <CheckCircle2 className="h-6 w-6 text-emerald-400" />
          </div>
          <h1 className="text-xl font-semibold text-white">Email verified</h1>
          <p className="mt-2 text-sm text-white/55">{message}</p>
          <Link to="/login" className="mt-6 inline-block text-sm font-medium text-indigo-400 hover:text-indigo-300">
            Continue to sign in
          </Link>
        </>
      )}

      {status === "error" && (
        <>
          <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-rose-500/10">
            <AlertCircle className="h-6 w-6 text-rose-400" />
          </div>
          <h1 className="text-xl font-semibold text-white">Verification failed</h1>
          <p className="mt-2 text-sm text-white/55">{message}</p>
          <Link to="/login" className="mt-6 inline-block text-sm font-medium text-indigo-400 hover:text-indigo-300">
            Back to sign in
          </Link>
        </>
      )}
    </GlassCard>
  );
}
