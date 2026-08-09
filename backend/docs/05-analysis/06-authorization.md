# 06 — Analysis Authorization

> Short by design — this module owns no public route, so there is very little authorization logic that actually belongs to it.

---

## 1. There Is No Direct Human or Agent Access to This Module

Per [`01-overview.md`](./01-overview.md) §6, the Analysis module exposes no endpoint of its own. It runs entirely as background processing (Poller + Queue Worker), triggered by nothing a Human or Agent directly calls.

```text
Human  → never calls anything in this module directly
Agent   → never calls anything in this module directly
```

---

## 2. Where Authorization for Prediction Data Actually Lives

Since a Prediction only ever becomes visible through `GET /observations/{id}` (per [`07-api-contract.md`](./07-api-contract.md)), and that route is owned entirely by the Observation module, **the authorization check that determines who can see a Prediction is the exact same check already fully specified in [`docs/backend/observation/06-authorization.md`](../04-observation/06-authorization.md).** There is nothing new to define here.

```text
Owner / Admin / Member  → can view any Prediction attached to an Observation they can
                             already view (Organization-scoped, per Observation's own rules)
Agent (API Key)           → cannot view any Prediction, ever — Agents have no read access
                               to Observations at all, let alone Predictions
```

---

## 3. Internal Process Trust, Not User Authorization

The Poller and Queue Worker run as trusted, internal, unauthenticated-by-user-identity processes — they act on behalf of the platform itself, not on behalf of any specific Human or Agent request. This is a standard, expected shape for background processing and requires no Role check, no JWT, no API Key — only the Backend↔ML Engine transport-level concern already covered in [`04-ml-client-contract.md`](./04-ml-client-contract.md) §3.

---

## 4. Summary

```text
Analysis Module Authorization

✔ No direct Human or Agent access to this module — it has no public route
✔ Prediction visibility is entirely governed by Observation's own authorization rules
✔ Background processing runs as a trusted internal process, not as any user's request
```
