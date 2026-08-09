# ADR-005: Multi-Tenancy via `organization_id`, Including Its Duplication Inside `observations`

| | |
|---|---|
| **Status** | ✅ Accepted (Frozen) |
| **Session** | Session 4 (core decision) + Session 6 (confirmation of denormalization) |
| **Affects** | Nearly every business table, especially `observations.organization_id` |

---

## Context

From day one, it was established that a client registers via "Register Organization," not "Register User." This makes SentinelX inherently a **Multi-Tenant SaaS** platform, where Organization is the root tenant:

```text
SentinelX
   │
   ├── Microsoft (Tenant)
   │      ├── Ahmed, Omar (Users)
   │      └── 12 Agents
   │
   ├── Google (Tenant)
   │      ├── John (User)
   │      └── 30 Agents
```

---

## Decision

### Part One: Organization Is the Root Entity
Nearly every business entity carries `organization_id`, either directly or indirectly:

```text
Organization
    │
    ├── Users            (organization_id direct)
    ├── Agents            (organization_id direct)
    │      └── API Keys   (via agent_id → agents.organization_id)
    └── Observations      (organization_id direct + agent_id)
            └── Predictions (via observation_id)
                    └── Alerts (via prediction_id)
```

### Part Two: Deliberate Denormalization in `observations`
Even though `organization_id` can be derived from `agent_id → agents.organization_id`, it was decided to duplicate `organization_id` **directly** inside the `observations` table.

---

## Rationale

### Why Is Organization the Root, Not User?
The real client of the platform is the organization, and the user is simply a person who "belongs to it" (`User belongs to Organization`), not the other way around. This architectural decision shapes the design of every relationship in the database — nearly every query logically begins by filtering on `organization_id`.

### Why Duplicate `organization_id` in `observations` (Denormalization)?
This decision might seem to violate academic normalization principles at first glance, but it's carefully calculated:

- **Nearly every query in the Dashboard starts with `organization_id`** — this is the real, frequent usage pattern of the platform.
- **It avoids a `JOIN` against the `agents` table** in most read operations, especially high-frequency queries like `GET /observations`.
- **It isolates the Observation from any future changes to Agent data** (such as theoretically moving an Agent between organizations — while not currently supported, the isolation protects against this scenario).
- **It makes the core queries directly and measurably faster and simpler.**

The rule that governed this decision:

> **Normalize by default... Denormalize only when it clearly improves real business queries.**

This isn't a sacrifice of academic purity — it's genuine **Production Engineering**, based on how the platform is actually used.

---

## Rejected Alternatives

| Alternative | Reason for Rejection |
|-------------|------------------------|
| Relying only on `agent_id` and deriving the organization via a JOIN | Slows down the platform's most frequent queries (Dashboard queries) and adds complexity to every query |
| Making User the root tenant instead of Organization | Contradicts the actual nature of the product — registration is fundamentally "Register Organization" |
| Multi-tenancy via a separate database per tenant (Database-per-Tenant) | Unjustified operational complexity for the current project scale (V1) — over-engineering |

---

## Consequences

- ✅ Every major Dashboard query performs well without needing a repeated JOIN.
- ✅ Logical isolation between each organization's data, easing the future application of security rules (Row-Level Security if needed).
- ✅ The architectural foundation is ready for any future evolution in the permissions system (RBAC) or billing (per-tenant billing).
- ⚠️ **Additional responsibility on the application layer:** any Observation creation operation must ensure `organization_id` actually matches `agent.organization_id` — the database does not automatically enforce this match via a constraint (there is no composite foreign key linking the two together in V1).
