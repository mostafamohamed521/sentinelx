# 05 — Dashboard Authorization

> Applies [`02-auth/06-authorization.md`](../02-auth/02-auth/06-authorization.md) to the Dashboard module. Same reasoning already established twice — [`docs/backend/observation/06-authorization.md`](../04-observation/06-authorization.md) §2 and [`docs/backend/alert/adr/ADR-003-all-roles-can-act-on-alerts.md`](../06-alert/adr/ADR-003-all-roles-can-act-on-alerts.md) — applied a third time, with even less ambiguity than either prior case.

---

## 1. The Decision

```text
Owner / Admin / Member  → can view GET /dashboard, identically
Agent (API Key)           → cannot access this endpoint at all
```

---

## 2. Why This Is the Easiest Authorization Decision in the Series So Far

`GET /dashboard` is purely a read-only composition of data every one of the three Roles can *already* independently see through the underlying endpoints: `GET /agents` (all Roles, per [`docs/backend/agent/05-authorization.md`](../03-agent/05-authorization.md) §2), `GET /observations` (all Roles, per [`docs/backend/observation/06-authorization.md`](../04-observation/06-authorization.md) §2), and `GET /alerts` (all Roles, per [`docs/backend/alert/04-authorization.md`](../06-alert/04-authorization.md) §4). Restricting the *aggregated* view to a subset of Roles who can already see every individual piece of it would be an inconsistent, arbitrary gate with no security purpose — it wouldn't hide anything a `Member` couldn't already assemble themselves by calling the underlying endpoints one at a time.

---

## 3. Organization Scoping

Every one of the four underlying contract calls is passed the same `organization_id`, resolved once from the `AuthenticatedIdentity` at the start of the request — never independently re-derived per contract call, and never accepted from any request parameter.

```text
❌ GET /dashboard?organization_id=xyz    (never — organization_id is never a query param)
✅ organization_id always comes from the authenticated JWT's resolved Organization
```

There is no cross-tenant leakage risk analogous to the `404 vs 403` question raised in every prior module, because `GET /dashboard` has no path parameter identifying a specific resource to be authorized against — it always returns *the caller's own* Organization's snapshot, by construction, with no way to request anyone else's.

---

## 4. Summary

```text
Dashboard Module Authorization

✔ All three Human Roles (Owner/Admin/Member) have identical, full access
✔ No Agent access — wrong guard, not a Role check
✔ No cross-tenant surface exists at all — the endpoint has no target resource
  parameter to scope against; it always reflects the caller's own Organization
```
