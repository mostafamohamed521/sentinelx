# ADR-001: The Core Is Framework-Agnostic — Only Adapters Change Per Framework

| | |
|---|---|
| **Status** | ✅ Accepted (Frozen) |
| **Source** | Session 3 (Agent Integration Models), confirmed empirically in Session 4.7 (The AI Agent Ecosystem) |
| **Affects** | The entire internal architecture, the repository structure, and every future framework addition |

---

## Context

Different AI Agent frameworks (CrewAI, LangGraph, AutoGen, the OpenAI Agents SDK) expose fundamentally different mechanisms for observing execution. A decision was needed on how to structure support for multiple frameworks without either (a) forcing a single integration mechanism onto every framework, or (b) rebuilding large parts of the SDK every time a new framework needed support.

---

## Decision

**The SDK Core never knows about any specific framework.** Framework-specific knowledge lives entirely inside a small, independent **Adapter** per framework:

```text
ASES Integration Layer
│
├── CrewAI Adapter
├── LangGraph Adapter
├── OpenAI Agents SDK Adapter
└── Generic Adapter
```

Adding a new framework means writing a new Adapter. It never means modifying the Core.

---

## Rationale

### Different Frameworks Genuinely Need Different Mechanisms
CrewAI favors Callbacks; other frameworks favor Middleware or Hooks (see [`06-integration-point-concept.md`](../06-integration-point-concept.md)). A single universal mechanism cannot satisfy all of them — the USB analogy in [`02-integration-philosophy.md`](../02-integration-philosophy.md) captures this directly: a uniform interface contract, satisfied differently by each device.

### This Was Proven, Not Just Designed
Session 4.7 evaluated CrewAI, LangGraph, AutoGen, Google ADK, and the OpenAI Agents SDK against this model and confirmed the same pattern holds for all of them: `Framework → [Framework] Adapter → ASES Core`, with the Core itself never changing.

### The Concrete Payoff
If the ML Engine, the Transport mechanism, or any other part of the system changes, only the relevant component changes. Symmetrically, if a new framework is added, only a new Adapter is added — a direct, structural guarantee that a fifth or tenth framework never requires an SDK rewrite.

---

## Rejected Alternatives

| Alternative | Reason for Rejection |
|-------------|------------------------|
| One universal integration mechanism for every framework | No such mechanism exists across all real frameworks — some expose Callbacks, others Middleware, others Hooks |
| Framework-specific SDK forks or variants | Would duplicate the entire Observation pipeline per framework, directly contradicting the Single Responsibility goals of the Core architecture |
| A Core that directly imports and understands each framework's API | Couples the Core's stability to every framework's own API stability, and makes every framework update a potential Core-breaking change |

---

## Consequences

- ✅ Adding a new framework is a contained, well-scoped task — write one Adapter, following the single-entry-point pattern in [`11-repository-architecture.md`](../11-repository-architecture.md).
- ✅ The Core's behavior, tests, and guarantees are stable regardless of how many frameworks are supported.
- ✅ Proven empirically across the actual current AI framework landscape (Session 4.7), not just asserted in the abstract.
- ⚠️ Every new Adapter must independently satisfy the Thin Adapter principle (see [`ADR-002-thin-adapter-principle.md`](./ADR-002-thin-adapter-principle.md)) — this architecture only holds if that discipline is maintained per Adapter.
