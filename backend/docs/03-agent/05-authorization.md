# 05 — Agent Authorization

> Applies [`02-auth/06-authorization.md`](../02-auth/02-auth/06-authorization.md) to the Agent module specifically. Introduces no new roles, no new permission model — RBAC with `Owner` / `Member` remains exactly as frozen.

---

## 1. Authorization Input, Per the Frozen Model

```text
Authenticated Identity  (Human User, resolved from JWT)
+
Requested Action        (e.g. "Archive Agent")
+
Resource                (a specific Agent, scoped to an Organization)
```

---

## 2. Permission Matrix

| Action | Owner | Member | Agent (API Key) |
|--------|:-----:|:------:|:----------------:|
| `GET /agents` (list) | ✅ | ✅ | ❌ |
| `GET /agents/{id}` | ✅ | ✅ | ❌ |
| `POST /agents` (create) | ✅ | ❌ | ❌ |
| `PATCH /agents/{id}` (update) | ✅ | ❌ | ❌ |
| `PATCH /agents/{id}/archive` | ✅ | ❌ | ❌ |
| `POST /agents/{id}/rotate-api-key` | ✅ | ❌ | ❌ |
| `GET /agents/{id}/observations` | ✅ | ✅ | ❌ |

This follows directly from [`02-auth/06-authorization.md`](../02-auth/02-auth/06-authorization.md) §7's own example table, which explicitly lists `Create Agent` and `Rotate API Key` as `Owner`-level day-to-day permissions, and its §5 definition of `Member`: *"Views results and works with them, without administrative privileges."*

**A Member can look at the fleet of Agents and drill into their Observation history — a Member cannot create, rename, or decommission one.**

---

## 3. Why an Agent Can Never Call These Endpoints

An authenticated Agent (via API Key) has exactly one Capability system-wide: `Submit Observation` ([`02-auth/06-authorization.md`](../02-auth/02-auth/06-authorization.md) §5). None of the Agent-management endpoints are reachable by an API-Key-authenticated request — this is enforced at the middleware level (these routes require the Human/JWT guard, never the Agent/API-Key guard), so the question of "does this Agent have permission" never even reaches a permission check. It fails at Authentication routing, not at Authorization.

---

## 4. Data Isolation (Organization Scoping)

Every single endpoint in this module is implicitly scoped to `organization_id = <caller's resolved Organization>` — never accepted as a request parameter. This is the same pattern already frozen for `organization_id` never being SDK-supplied ([`02-auth/05-api-keys.md`](../02-auth/02-auth/05-api-keys.md) §5) and mirrors the general REST Engineering Workflow principle of Scoped Queries:

```text
❌ Agent::find($agentId)->update(...)
✅ $organization->agents()->where('id', $agentId)->firstOrFail()->update(...)
```

**404, not 403, for an Agent that exists but belongs to a different Organization** — Security Through Obscurity, exactly the pattern already established project-wide (see the Engineering Workflow's `Pattern 7`). A `403` would confirm the Agent ID exists somewhere on the platform; a `404` reveals nothing.

---

## 5. Where the Check Executes

```text
Request
    ↓
Authentication (JWT → User → Organization)
    ↓
Authorization (Role check: is this action Owner-only? does this User's Role satisfy it?)
    ↓
Agent Module Business Logic (Application layer Action)
```

Not inside the Controller, not inside the Action itself — Authorization is a distinct middleware/gate step that runs before the Action is ever invoked, exactly as [`02-auth/06-authorization.md`](../02-auth/02-auth/06-authorization.md) §9 specifies.

---

## 6. Summary

```text
Agent Module Authorization

✔ Owner  → full CRUD + lifecycle + key rotation trigger
✔ Member → read-only (list, view, view observations)
✔ Agent  → no access whatsoever to this module's endpoints
✔ Every query scoped to Organization — 404 on cross-tenant access, never 403
✔ No new roles introduced
```
