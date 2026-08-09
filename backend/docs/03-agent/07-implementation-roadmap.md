# 07 — Agent Module Implementation Roadmap

> Converts everything designed in this folder into a build plan, following the exact same rule set as [`02-auth/09-implementation-roadmap.md`](../02-auth/02-auth/09-implementation-roadmap.md) and the Layer order from [`06-implementation-layers.md`](../00-backend_architecture/docs/backend/backend-architecture/06-implementation-layers.md).

---

## 1. Where This Sits

```text
Sprint 0 — Foundation           ✅ Done
Sprint 1 — Identity Foundation  ✅ Done
Sprint 2 — Agent Foundation     🟢 This roadmap
```

**Definition of Done for Sprint 2** (per [`08-sprint-roadmap.md`](../00-backend_architecture/docs/backend/backend-architecture/08-sprint-roadmap.md)):

> User → Create Agent → Generate API Key

By the end of this Sprint, a Human can log in, create an Agent, and receive a usable API Key ready to hand to the SDK — a complete, demoable, testable increment.

---

## 2. Build Order (Layer by Layer, Agent Module Only)

```text
1. Domain
   ├── Agent (domain object / invariants from 02-domain.md §7)
   └── AgentPolicy (archive-idempotency rule, name-uniqueness rule)

2. Infrastructure (Persistence)
   ├── AgentModel (Eloquent)
   ├── AgentRepository
   │     ├── create()
   │     ├── findById(id, organizationId)
   │     ├── findByName(organizationId, name)
   │     ├── update()
   │     ├── archive()
   │     └── touchLastSeen(agentId, timestamp)   ← the one method Observation
   │                                                  is later allowed to call
   └── AgentLookupContract (interface) + its implementation
         — the only surface Authentication's API Key submodule may call
           (see 04-api-key-coordination.md §2)

3. Application (Actions)
   ├── CreateAgentAction
   ├── UpdateAgentAction
   ├── ArchiveAgentAction        (also dispatches AgentArchived)
   ├── ListAgentsAction
   └── GetAgentAction

4. Presentation
   └── AgentResource             (never includes credential fields)

5. API
   ├── AgentController
   │     ├── index()   → ListAgentsAction
   │     ├── store()    → CreateAgentAction
   │     ├── show()     → GetAgentAction
   │     ├── update()   → UpdateAgentAction
   │     └── archive()  → ArchiveAgentAction
   ├── StoreAgentRequest / UpdateAgentRequest (FormRequests)
   └── Routes (all under auth:jwt + role:owner|member per endpoint, see
       05-authorization.md §2)
```

**Note the order:** Domain and Infrastructure before Application, Application before Presentation/API — same bottom-up order as [`02-auth/09-implementation-roadmap.md`](../02-auth/02-auth/09-implementation-roadmap.md) §4.

---

## 3. What Happens in Parallel, Inside Authentication (Not This Module's Code, But Required for Sprint 2's DoD)

```text
Authentication\ApiKey\Application
    ├── GenerateApiKeyAction   (calls AgentLookupContract, not any Agent internals)
    └── RotateApiKeyAction

Authentication\ApiKey\Listeners
    └── RevokeKeysOnAgentArchived  (subscribes to AgentArchived)
```

These are **Authentication module** work items — listed here only so whoever picks up Sprint 2 sees the whole picture and doesn't build the Agent module in isolation, unaware that a second, cooperating module must ship in the same Sprint for the Definition of Done to be met.

---

## 4. Tests Required (Following the Engineering Workflow's Five Categories)

```text
1. Happy Path
   ✔ Owner can create an Agent
   ✔ Owner can update an Agent's metadata
   ✔ Owner can archive an Agent
   ✔ Member can list/view Agents

2. Edge Case
   ✔ Creating an Agent with a duplicate name within the same Organization → 409
   ✔ Creating an Agent with the same name in a DIFFERENT Organization → 201 (allowed)
   ✔ Archiving an already-Archived Agent → 409, not silent success
   ✔ PATCH with an empty body → 422

3. Business Rule
   ✔ organization_id is always derived from the JWT, never from the request body
   ✔ status can never be set directly via PATCH /agents/{id}
   ✔ Archiving an Agent triggers AgentArchived and results in its API Key being
     REVOKED (integration test spanning both modules)

4. Authorization
   ✔ Member attempting POST /agents → 403
   ✔ Member attempting PATCH /agents/{id} → 403
   ✔ Member attempting archive → 403
   ✔ Unauthenticated request to any endpoint → 401

5. Data Isolation
   ✔ Organization A cannot GET/PATCH/archive an Agent belonging to Organization B → 404
   ✔ Organization A's Agent list never includes Organization B's Agents
```

---

## 5. Sprint 2 Exit Checklist

```text
☐ agents table already exists (Stage 1/Database — no new migration needed)
☐ AgentLookupContract implemented and consumed by Authentication
☐ AgentArchived event implemented and consumed by Authentication
☐ All 5 Agent-module endpoints implemented and passing tests
☐ rotate-api-key endpoint implemented (Authentication module) and passing tests
☐ Postman collection updated with the Agent folder
☐ docs/backend/agent/ (this folder) marked Frozen once code matches it exactly
```
