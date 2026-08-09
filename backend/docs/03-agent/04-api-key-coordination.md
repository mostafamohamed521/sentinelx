# 04 — Agent ↔ API Key Coordination

> This file exists because "who calls whom" between Agent and Authentication is the single easiest thing to get wrong in Stage 2. It resolves entirely from [`05-module-dependencies.md`](../00-backend_architecture/docs/backend/backend-architecture/05-module-dependencies.md) §4 — nothing here is a new decision.

---

## 1. The Dependency Direction, Restated

```text
API Key (submodule of Authentication)
    ↓ depends on
Agent
    ↓ depends on
Organization
```

Read this as: **API Key is allowed to know Agent exists and ask it questions. Agent is not allowed to know API Key exists.**

---

## 2. What "Allowed to Know" Looks Like in Code

The API Key submodule never runs its own ad-hoc query against the `agents` table. It calls a narrow, explicit interface that the Agent module exposes for exactly this purpose:

```text
Authentication\ApiKey\Application\GenerateApiKeyAction
    │
    │ calls (allowed — API Key depends on Agent)
    ▼
Agent\Application\Contracts\AgentLookupContract
    ├── findActiveAgentForOrganization(agentId, organizationId): Agent | null
    └── (read-only — no write methods on this contract)
```

This `AgentLookupContract` interface — defined and implemented inside the **Agent module's** own Application/Domain layer, but consumed by Authentication — is the *entire* legal surface between the two modules for this direction. Nothing else.

---

## 3. What the Agent Module Is Never Allowed to Call

```text
Agent\Application\CreateAgentAction
    │
    ✘ never calls → Authentication\ApiKey\Application\GenerateApiKeyAction
    ✘ never imports anything from the Authentication module namespace
    ✘ never reads api_keys, directly or via any repository
```

If a future PR adds any of the above inside the Agent module, that PR violates the frozen dependency direction and must be rejected in review — full stop, regardless of how convenient it looks in the moment.

---

## 4. The Event Channel (Agent → Authentication, the *only* reverse-looking interaction)

Agent is still allowed to **announce facts about itself** without depending on who's listening — this is the standard Modular Monolith escape hatch for "the lower module needs to notify the higher one":

```text
Agent\Application\ArchiveAgentAction
    │
    ├── writes agents.status = ARCHIVED
    │
    └── dispatches AgentArchived(agentId, organizationId)
              (a plain domain event — Agent module has zero knowledge
               of who, if anyone, is listening)
              │
              ▼
    Authentication\ApiKey\Listeners\RevokeKeysOnAgentArchived
        (lives in Authentication; subscribes to Agent's event;
         Agent module never imports this listener, never calls it,
         never knows it exists)
```

This preserves the rule perfectly: Agent depends on nothing new (an event dispatch is not a dependency on a consumer — nobody has to be listening for `ArchiveAgentAction` to complete correctly), while Authentication (which is *already* allowed to depend on Agent) reacts to it.

---

## 5. Summary Table

| Direction | Allowed? | Mechanism |
|-----------|----------|-----------|
| API Key submodule → reads Agent state | ✅ Allowed | `AgentLookupContract` (read-only interface owned by Agent) |
| API Key submodule → writes to `agents` | ❌ Never | N/A — no write method exists on the contract |
| Agent module → calls API Key Actions | ❌ Never | N/A — Agent has no reference to the Authentication namespace |
| Agent module → notifies API Key submodule of Archive | ✅ Allowed | Domain event (`AgentArchived`), fire-and-forget |
| Agent module → reads `api_keys` table | ❌ Never | N/A |

---

## 6. Why This Matters Beyond "Following the Rules"

Per [`05-module-dependencies.md`](../00-backend_architecture/docs/backend/backend-architecture/05-module-dependencies.md) §4: *"API Keys could be replaced by any other credential mechanism tomorrow without changing anything about the Agent entity itself."* This is the concrete payoff — if SentinelX ever adds mTLS certificates as an alternative Agent credential in V2, the Agent module's code (`CreateAgentAction`, `ArchiveAgentAction`, the `agents` table) does not change at all. Only a new submodule inside Authentication is added, and it wires itself into the exact same `AgentLookupContract` and the exact same `AgentArchived` event that already exist.
