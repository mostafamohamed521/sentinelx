// Authentication API — matches the provided endpoint contract exactly:
//   POST /v1/auth/register
//   POST /v1/auth/login
//   POST /v1/auth/logout
//   GET  /v1/auth/me
//   POST /v1/auth/refresh
//   GET  /v1/auth/verify-email/{id}/{hash}
//   POST /v1/auth/email/resend
//
// Two important, confirmed-by-the-contract shapes that differ from what an
// earlier pass of this file assumed:
//   1. POST /auth/login returns ONLY { access_token, token_type, expires_in }
//      — no `user` object. The caller (AuthContext) must follow up with
//      GET /auth/me to learn who just signed in.
//   2. POST /auth/register returns the created UserResource + a message —
//      no access_token. The backend requires a verified email before
//      LoginUserAction will ever issue a JWT, so a freshly-registered
//      account cannot be logged in immediately; the UI must send them to
//      "check your email" instead of the dashboard.
//
// forgot-password / reset-password are NOT part of the provided endpoint
// set. They are kept here, MOCK_MODE-only in practice, as an extended/
// proposed surface the Login/ForgotPassword pages already link to — calling
// them against a real Backend will 404 until/unless that contract is
// confirmed. Do not treat their shapes below as verified.

import { apiFetch, MOCK_MODE, ApiError } from "../apiClient.js";

const FAKE_LATENCY = 550;

// Shaped like the real UserResource (App\...\Presentation\UserResource):
// id, organization_id, full_name, email, role, status, email_verified_at,
// last_login_at, created_at. Role/status are the Backend's real uppercase
// enum values (UserRole::OWNER, UserStatus::ACTIVE).
const DEMO_USER = {
  id: "usr_123",
  organization_id: "org_456",
  full_name: "Ahmed",
  email: "ahmed@futurebank.com",
  role: "OWNER",
  status: "ACTIVE",
  email_verified_at: "2026-07-01T09:00:00Z",
  last_login_at: "2026-07-25T08:00:00Z",
  created_at: "2026-06-01T09:00:00Z",
};

function delay(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function fakeToken(prefix) {
  return `${prefix}_${Math.random().toString(36).slice(2)}${Date.now().toString(36)}`;
}

// POST /v1/auth/login — public, throttled 5/min/IP.
// Response: { access_token, token_type: "bearer", expires_in }
export async function login({ email, password }) {
  if (MOCK_MODE) {
    await delay(FAKE_LATENCY);
    if (!email || !password) throw new ApiError("VALIDATION_ERROR", "Email and password are required");
    // Demo credentials for local testing: any email + password of 4+ chars succeeds.
    // The real Backend returns the identical generic message for every failure
    // reason (wrong password, unverified email, disabled user, ...) — see
    // LoginUserAction — so the mock mirrors that same generic message.
    if (password.length < 4) throw new ApiError("AUTHENTICATION_FAILED", "Authentication failed.");
    return { access_token: fakeToken("eyJhbGci"), token_type: "bearer", expires_in: 3600 };
  }
  return apiFetch("/auth/login", { method: "POST", body: { email, password } });
}

// POST /v1/auth/register — public, throttled 10/min/IP.
// Body: organization_name, full_name, email, password, password_confirmation
// (field names match RegisterRequest's validated set exactly).
// Response: { data: UserResource, message } — no token; the account still
// needs email verification before it can log in.
export async function signup({ organization_name, full_name, email, password, password_confirmation }) {
  if (MOCK_MODE) {
    await delay(FAKE_LATENCY);
    if (!organization_name || !full_name || !email || !password) {
      throw new ApiError("VALIDATION_ERROR", "All fields are required");
    }
    if (password !== password_confirmation) {
      throw new ApiError("VALIDATION_ERROR", "Password confirmation does not match");
    }
    if (password.length < 8) throw new ApiError("VALIDATION_ERROR", "Password must be at least 8 characters");
    return {
      data: {
        id: "usr_" + Math.random().toString(36).slice(2, 8),
        organization_id: "org_" + Math.random().toString(36).slice(2, 8),
        full_name,
        email,
        role: "OWNER",
        status: "ACTIVE",
        email_verified_at: null,
        last_login_at: null,
        created_at: new Date().toISOString(),
      },
      message: "Organization created. Please check your email to verify your account.",
    };
  }
  return apiFetch("/auth/register", {
    method: "POST",
    body: { organization_name, full_name, email, password, password_confirmation },
  });
}

// POST /v1/auth/logout — requires Bearer token. Client-side only in V1
// (no server-side token blacklist), so this call is best-effort: the
// caller clears the local session regardless of the outcome.
export async function logout() {
  if (MOCK_MODE) {
    await delay(200);
    return { message: "Logged out." };
  }
  return apiFetch("/auth/logout", { method: "POST" });
}

// GET /v1/auth/me — requires Bearer token. Response: { data: UserResource }.
export async function getCurrentUser() {
  if (MOCK_MODE) {
    await delay(300);
    return DEMO_USER;
  }
  const res = await apiFetch("/auth/me");
  return res.data;
}

// POST /v1/auth/refresh — requires Bearer token, re-issues a token from the
// CURRENT token (no refresh_token in the body — the real Backend only ever
// issues a single JWT access token). Response: { access_token, token_type, expires_in }.
export async function refreshToken() {
  if (MOCK_MODE) {
    await delay(300);
    return { access_token: fakeToken("eyJhbGci"), token_type: "bearer", expires_in: 3600 };
  }
  return apiFetch("/auth/refresh", { method: "POST" });
}

// GET /v1/auth/verify-email/{id}/{hash}?signature=...&expires=... — public,
// signed URL (no Bearer token). `queryString` must carry the exact
// signature/expires query params from the email link untouched, since the
// Backend's `signed` middleware validates them against the full URL.
// Response: { message: "Email verified successfully." | "Email already verified." }
export async function verifyEmail(id, hash, queryString = "") {
  if (MOCK_MODE) {
    await delay(400);
    return { message: "Email verified successfully." };
  }
  return apiFetch(`/auth/verify-email/${id}/${hash}${queryString}`, { method: "GET" });
}

// POST /v1/auth/email/resend — requires Bearer token, throttled 5/min/IP.
// Note: per the documented contract, this route requires auth:api, but
// LoginUserAction blocks issuing a token to an unverified user — so in
// practice there is no documented flow that reaches this endpoint for a
// user who actually needs it. Implemented faithfully for completeness; see
// the integration report for this gap.
// Response: { message: "Verification link sent." | "Email already verified." }
export async function resendVerificationEmail() {
  if (MOCK_MODE) {
    await delay(FAKE_LATENCY);
    return { message: "Verification link sent." };
  }
  return apiFetch("/auth/email/resend", { method: "POST" });
}

// PATCH /v1/me — updates only the caller's own full_name (email/role are
// not editable through this endpoint at all — not accepted-and-ignored,
// simply absent from the contract). Response: { data: UserResource }.
export async function updateProfile({ full_name }) {
  if (MOCK_MODE) {
    await delay(400);
    if (!full_name) throw new ApiError("VALIDATION_ERROR", "Full name is required");
    return { ...DEMO_USER, full_name };
  }
  const res = await apiFetch("/me", { method: "PATCH", body: { full_name } });
  return res.data;
}

// POST /v1/me/change-password — requires the current password re-confirmed.
// Response: { status: "success", message } (not wrapped in `data`).
export async function changePassword({ current_password, new_password, new_password_confirmation }) {
  if (MOCK_MODE) {
    await delay(500);
    if (!current_password || !new_password) throw new ApiError("VALIDATION_ERROR", "All fields are required");
    if (new_password !== new_password_confirmation) throw new ApiError("VALIDATION_ERROR", "Password confirmation does not match");
    if (current_password.length < 4) throw new ApiError("UNAUTHORIZED", "The provided current password is incorrect.");
    return { status: "success", message: "Password changed successfully" };
  }
  return apiFetch("/me/change-password", {
    method: "POST",
    body: { current_password, new_password, new_password_confirmation },
  });
}

// --- NOT part of the provided endpoint contract -----------------------
// Kept for the existing ForgotPassword/ResetPassword pages. Real-mode calls
// will hit a Backend route that has not been confirmed to exist.

export async function forgotPassword({ email }) {
  if (MOCK_MODE) {
    await delay(FAKE_LATENCY);
    if (!email) throw new ApiError("VALIDATION_ERROR", "Email is required");
    return { message: "Password reset link sent" };
  }
  return apiFetch("/auth/forgot-password", { method: "POST", body: { email } });
}

export async function resetPassword({ token, new_password }) {
  if (MOCK_MODE) {
    await delay(FAKE_LATENCY);
    if (!token || !new_password) throw new ApiError("VALIDATION_ERROR", "Token and new password are required");
    if (new_password.length < 8) throw new ApiError("WEAK_PASSWORD", "Password must be at least 8 characters");
    return { message: "Password updated successfully" };
  }
  return apiFetch("/auth/reset-password", { method: "POST", body: { token, new_password } });
}
