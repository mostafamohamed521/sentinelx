// Central HTTP client for the SentinelX REST API.
//
// Built to match the frozen API documentation exactly:
//   - Base URL: /api/v1                              (API_OVERVIEW.md, API_CONVENTIONS.md)
//   - Error shape: { error: { code, message, details } }  (API_ERROR_CODES.md)
//   - Two auth mechanisms: JWT (dashboard users) / API Key (SDK — not used from this app)
//   - Every timestamp is ISO 8601 UTC                  (API_CONVENTIONS.md)
//   - DELETE is not used for business entities in V1   (API_CONVENTIONS.md)
//
// MOCK_MODE lets the whole frontend run against realistic in-memory data
// (src/lib/mockDb.js) instead of a live Backend. Every resource module under
// src/lib/api/*.js has two code paths — mock and real — gated by this flag.
//
// Driven by VITE_MOCK_MODE (see .env.example) rather than hardcoded, so the
// real code path is actually exercised in normal development instead of
// silently going untested indefinitely — see integration audit CONTRACT-005
// through CONTRACT-010 / PERF-001, all of which trace back to this flag
// never having been disabled in this codebase's history. Defaults to real
// mode (false) when unset; set VITE_MOCK_MODE=true locally for demo/offline
// use.
export const MOCK_MODE = (import.meta.env?.VITE_MOCK_MODE ?? "false") === "true";

export const API_BASE_URL = import.meta.env?.VITE_API_BASE_URL || "/api/v1";

/**
 * A normalized API error. Always carries a machine-readable `code` (per
 * API_ERROR_CODES.md) in addition to the standard Error `message`, so callers
 * can branch on `err.code` instead of parsing message strings.
 */
export class ApiError extends Error {
  constructor(code, message, details = {}) {
    super(message);
    this.name = "ApiError";
    this.code = code;
    this.details = details;
  }
}

function getStoredToken() {
  return typeof window !== "undefined" ? localStorage.getItem("sentinelx_access_token") : null;
}

// PERF-005: apiFetch previously had no timeout at all, inheriting the
// browser's own effectively-unbounded default. Most Backend endpoints are
// fast; the async Observation pipeline is designed to be slow and is
// already handled by polling (Phase 1), not a single long-lived request.
const REQUEST_TIMEOUT_MS = 20_000;

// FAILURE-004: one bounded, silent automatic retry for a transient
// network-level blip — a supplement to, not a replacement for, the
// existing manual "Retry" button every page already wires up via
// PageError/onRetry (see frontend/src/components/ui/PageState.jsx).
const RETRY_DELAY_MS = 1_500;

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

async function attemptFetch(path, { method, body, headers, token }) {
  const controller = new AbortController();
  const timeoutId = setTimeout(() => controller.abort(), REQUEST_TIMEOUT_MS);

  try {
    return await fetch(`${API_BASE_URL}${path}`, {
      method,
      headers: {
        "Content-Type": "application/json",
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
        ...headers,
      },
      body: body ? JSON.stringify(body) : undefined,
      signal: controller.signal,
    });
  } catch (cause) {
    // ERROR-006: a raw, un-normalized browser error (a dropped connection,
    // DNS failure, or this function's own timeout abort) — normalized into
    // the same ApiError shape as an HTTP-response failure, so calling code
    // can always branch on `err.code` regardless of cause, distinguishing
    // "couldn't reach the server" from "the server rejected the request."
    // Mirrors how the Backend already distinguishes its own transport-vs-
    // contract ML failures (MLCommunicationException vs
    // InvalidMlResponseException).
    const isTimeout = cause?.name === "AbortError";
    throw new ApiError(
      isTimeout ? "TIMEOUT" : "NETWORK_ERROR",
      isTimeout ? "The request timed out." : "Unable to reach the server.",
      {},
    );
  } finally {
    clearTimeout(timeoutId);
  }
}

/**
 * Thin fetch wrapper: attaches the JWT, parses the standard error envelope,
 * and throws an ApiError on any non-2xx response so calling code can use a
 * single try/catch regardless of which endpoint failed.
 */
export async function apiFetch(path, { method = "GET", body, headers = {} } = {}) {
  const token = getStoredToken();

  let res;
  try {
    res = await attemptFetch(path, { method, body, headers, token });
  } catch (err) {
    // Exactly one silent retry, and only for a genuine network-level
    // failure — never for a timeout (the server may still be processing
    // the first attempt) and never for an HTTP-response failure (retrying
    // a rejected request changes nothing).
    if (err instanceof ApiError && err.code === "NETWORK_ERROR") {
      await sleep(RETRY_DELAY_MS);
      res = await attemptFetch(path, { method, body, headers, token });
    } else {
      throw err;
    }
  }

  if (res.status === 204) return null;

  let payload = null;
  try {
    payload = await res.json();
  } catch {
    // no JSON body (e.g. some error responses) — fall through with payload = null
  }

  if (!res.ok) {
    const err = payload?.error || {};
    throw new ApiError(err.code || "UNKNOWN_ERROR", err.message || res.statusText, err.details || {});
  }

  return payload;
}

/**
 * Builds a query string from a params object, skipping undefined/null/empty
 * values, matching the filtering/sorting/pagination conventions documented
 * for collection endpoints (page, per_page, sort, search, status, etc).
 */
export function toQueryString(params = {}) {
  const search = new URLSearchParams();
  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== "") {
      search.set(key, value);
    }
  });
  const qs = search.toString();
  return qs ? `?${qs}` : "";
}
