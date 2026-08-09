# 04 — Alert Authorization

> Applies [`02-auth/06-authorization.md`](../02-auth/02-auth/06-authorization.md) (Baseline v2.0 — `Owner`/`Admin`/`Member`) to the Alert module. **No frozen document provides a per-endpoint Role matrix for Alerts** — this file makes that call explicitly, with reasoning, exactly like the two gaps already resolved in [`02-domain.md`](./02-domain.md).

---

## 1. The Gap, Named

[`02-auth/06-authorization.md`](../02-auth/02-auth/06-authorization.md) §5 lists `View Alerts` only as an illustrative example of "Human Permissions" in general — it does not say which Role(s) may view, and says nothing at all about acknowledge/resolve. No other frozen document fills this in either.

---

## 2. The Decision

```text
Owner / Admin / Member  → can view (list, single)
Owner / Admin / Member  → can acknowledge
Owner / Admin / Member  → can resolve
Agent (API Key)           → cannot access this module's endpoints at all
```

**All three Human Roles have identical access to every Alert action.**

---

## 3. Why This Is the Correct Reading, Not an Arbitrary Choice

Per [`02-auth/06-authorization.md`](../02-auth/02-auth/06-authorization.md) §7's own definition table: **`Member` — "Views results and works with them."** Acknowledging and resolving an Alert *is* working with a result — it is the single clearest example of exactly the activity that definition describes. There is no comparable phrase anywhere in the frozen Role definitions suggesting acknowledge/resolve requires elevated privilege the way, say, creating or archiving an Agent does (which was explicitly Owner-gated in Stage 2, per real frozen text that existed at the time: *"day-to-day platform operation (create Agents, rotate API Keys...)"*). Restricting Alert handling to `Owner`/`Admin` only would actively work against the platform's core value proposition — a security team where only two of three seats can act on an incident is a worse security posture, not a more careful one, and nothing in `ADR-011-Alert-Generation-Policy` or anywhere else suggests this restriction was intended.

This mirrors the reasoning already applied identically to Observation viewing in [`docs/backend/observation/06-authorization.md`](../04-observation/06-authorization.md) §2: *"Viewing security history is not an administrative privilege; it is the core value the platform provides to every seat in the Organization."* Handling an Alert extends that same logic from viewing to acting.

---

## 4. Permission Matrix

| Action | Owner | Admin | Member | Agent (API Key) |
|--------|:-----:|:-----:|:------:|:----------------:|
| `GET /alerts` (list) | ✅ | ✅ | ✅ | ❌ |
| `GET /alerts/{id}` | ✅ | ✅ | ✅ | ❌ |
| `PATCH /alerts/{id}/acknowledge` | ✅ | ✅ | ✅ | ❌ |
| `PATCH /alerts/{id}/resolve` | ✅ | ✅ | ✅ | ❌ |

**An Agent, regardless of which Agent, can never reach any endpoint in this module.** Per [`02-auth/06-authorization.md`](../02-auth/02-auth/06-authorization.md) §12, an Agent's single Capability is `Submit Observation` — there is no Capability that grants Alert access, and Alert endpoints only accept the JWT guard.

---

## 5. Organization Scoping

Identical pattern to every module so far:

```text
❌ Alert::find($alertId)
✅ $organization->alerts()->where('id', $alertId)->firstOrFail()
```

**404, not 403, for an Alert belonging to a different Organization** — Security Through Obscurity, the same rule applied consistently since Stage 2. An Alert's Organization is derived through its Prediction → Observation chain (denormalization is not needed here the way it was for `observations.organization_id`, since Alert volume is far lower and this path is not a high-frequency query — this module's own `AlertRepository` scoping query joins through to `observations.organization_id` rather than duplicating the column, unless implementation reveals a real performance need not currently documented anywhere).

---

## 6. `acknowledged_by` / `resolved_by` — Always the Authenticated Identity

Same discipline as every "who did this" field established previously: `acknowledged_by` and `resolved_by` are always set from the authenticated JWT's resolved User ID — never accepted from the request body, never client-suppliable. Both `PATCH` endpoints in this module take no meaningful request body at all beyond, at most, an optional note field not currently specified in any frozen document (and therefore not built — see [`06-api-contract.md`](./06-api-contract.md)).

---

## 7. Summary

```text
Alert Module Authorization

✔ All three Human Roles (Owner/Admin/Member) have identical access to every action —
  a deliberate, reasoned gap-fill, not an oversight
✔ No Agent access to this module, structurally (wrong guard, not a Role check)
✔ Every query scoped to Organization — 404 on cross-tenant access, never 403
✔ acknowledged_by / resolved_by always server-derived from the authenticated identity
```
