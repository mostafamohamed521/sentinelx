# ADR-003: Identity and API Key Become Submodules of Authentication, Not Standalone Modules

| | |
|---|---|
| **Status** | ✅ Accepted (Documentation Baseline v2.0) |
| **Conflict Source** | Cross-Review, Conflict 3 |
| **Affects** | The module set (Session 3), module responsibilities (Session 4), and module dependencies (Session 5) |

---

## Context

Documentation Baseline v1.0 described the backend at a high level, in terms of "Services" (`Authentication`, `Observation Service`, `Prediction Service`, `Alert Service`, `Dashboard Service`) — six components total, with no dedicated Company/Organization, API Key, or Audit component.

The Backend Architecture sessions produced a full Modular Monolith design with **9 standalone modules**: `Identity`, `Company` (now `Organization`, see ADR-001), `Agent`, `API Key`, `Observation`, `Analysis`, `Alert`, `Dashboard`, `Audit`.

---

## Decision

The 9-module design is not reverted, but it **is** simplified by one module: `Identity` and `API Key` are merged into a single `Authentication` module, each as an internal submodule.

```text
Final Module Set (8 modules):

Authentication
├── Identity (submodule)   — Users, Login, JWT, Roles
└── API Key (submodule)    — Generate, Rotate, Revoke, Validate

Organization
Agent
Observation
Analysis
Alert
Dashboard
Audit
```

`Prediction` (the v1.0 term) is retained as **`Analysis`** (the newer, more accurate term — see rationale below). `Audit`, absent from v1.0, is kept as a full module, since the platform's own security-audit requirement genuinely needs it.

---

## Rationale

### Why Not Revert to the Old 6-"Service" Model?
The old model is too coarse for actual implementation — it has no place to put Organization management, no independent lifecycle for API Keys distinct from Agents, and no Audit trail at all. The newer, more granular module design solves real problems the old model didn't address.

### Why Merge Identity and API Key Specifically?
Both submodules exist to answer the exact same higher-level business question: *"is this request coming from who it claims to be?"* — just for two different actor types (Human vs. Agent). Keeping them under one module boundary reduces the total module count and keeps `Authentication` as the single place anyone looks for "how does this platform prove who's making a request," while their genuinely different data ownership and dependency chains (Identity → Organization; API Key → Agent → Organization) remain fully documented and enforced internally — see [`04-module-responsibilities.md`](../04-module-responsibilities.md) and [`05-module-dependencies.md`](../05-module-dependencies.md).

### Why Rename `Prediction` to `Analysis`?
`Prediction` is only the *output* of the module's work. `Analysis` describes the entire process — sending the request to ML, receiving the response, storing the Prediction, and storing Evidence. The more accurate name was adopted going forward.

---

## Rejected Alternatives

| Alternative | Reason for Rejection |
|-------------|------------------------|
| Keep 9 standalone modules (`Identity` and `API Key` fully independent) | Adds a module boundary for two submodules that both exist to answer the same "who is this?" question — unnecessary fragmentation |
| Revert fully to the v1.0 6-"Service" model | Loses real, needed capabilities: no Organization module, no independent API Key lifecycle, no Audit module |
| Merge `API Key` into `Agent` instead of `Authentication` | Breaks the principle established in the Authentication design that Credential is a distinct concern from Identity (see the Authentication documentation's ADR-002) — API Keys are a Credential, not an Agent attribute |

---

## Consequences

- ✅ 8 modules total — fewer than the original 9, without losing any actual capability.
- ✅ `Authentication` becomes the single, obvious place to look for anything related to proving identity, for either actor type.
- ✅ The internal submodule split preserves the important distinction that Identity depends on Organization while API Key depends on Agent — this is not lost, just organized under one module folder.
- ⚠️ Implementation-wise, the `Authentication` module's internal structure (see [`06-implementation-layers.md`](../06-implementation-layers.md)) needs clear internal separation between its two submodules to avoid them blurring into one large, undifferentiated codebase over time.
