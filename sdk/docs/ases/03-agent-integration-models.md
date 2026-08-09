# 03 — Agent Integration Models

> Source concept: Session 3. This document turns the philosophy in [`02-integration-philosophy.md`](./02-integration-philosophy.md) into a concrete, prioritized set of mechanisms, and defines exactly which ones ship in V1.

---

## 1. Four Models, Not More

After surveying how AI Agent frameworks actually expose extensibility, exactly four Integration Models emerge. Any integration mechanism that exists today falls under one of these four — there is no fifth category waiting to be discovered.

---

## 2. Model 1 — Callback Integration (Primary)

The framework itself calls a function whenever something happens.

```text
Framework
    ↓
Event occurs (e.g., a Tool starts)
    ↓
Framework calls a registered Callback
    ↓
ASES Adapter
```

The SDK does no monitoring here — it is purely a **listener**, notified by the framework.

**Strengths:** simple, low overhead, stable, built on official APIs.
**Weakness:** only works if the framework actually exposes callbacks.

---

## 3. Model 2 — Middleware Integration (Future)

Every request and response passes through a shared interception layer.

```text
Agent → Middleware → ASES SDK → LLM
```

This yields richer data — prompt content, response content, duration, token counts — because it sits directly in the request/response path.

**Weakness:** not every framework offers a middleware layer.

---

## 4. Model 3 — Decorator Integration (Future)

For a customer's own functions:

```python
@ases.monitor
def search_web():
    ...
```

The moment the decorated function runs, an Event is recorded.

**Strengths:** excellent fit for custom, hand-written Agents.
**Weakness:** doesn't map naturally onto large, pre-built frameworks.

---

## 5. Model 4 — Manual API (Generic Fallback)

For Agents with no framework at all — no callback, no middleware, no decorator hook — a direct, explicit call on a `GenericAdapter` instance:

```python
adapter.emit(...)
```

This is the **fallback of last resort**, guaranteeing that even a fully custom Agent can integrate, as long as its owner is willing to add one explicit call.

---

## 6. Priority Order

```text
1. Callback     ★★★★★
2. Middleware    ★★★★☆
3. Decorator      ★★★★☆
4. Manual API       ★★★☆☆
```

---

## 7. V1 Scope

> **V1 supports Callback Integration and the Manual API only.**

Together, these two cover roughly 90% of realistic integration scenarios. Middleware and Decorator integration are deliberately deferred to V2 — this is "Production Level without Over-Engineering" applied directly: build what covers the overwhelming majority of cases first, and add the rest once real demand appears.

```text
✅ Callback Integration     — V1
✅ Manual API                — V1
🟡 Middleware Integration      — V2
🟡 Decorator Integration         — V2
```

---

## 8. The Architectural Decision That Follows From This

> **The Core SDK does not know CrewAI. It does not know LangGraph. Only the Adapter knows the framework it was built for.**

```text
ASES Integration Layer
│
├── CrewAI Adapter
├── LangGraph Adapter
├── OpenAI Agents SDK Adapter
└── Generic Adapter
```

This means the Core's shape never has to change when a new framework is supported — see [`ADR-001-adapter-based-framework-strategy.md`](./adr/ADR-001-adapter-based-framework-strategy.md) for the full reasoning.

---

## 9. Does the Customer Choose a "Model"?

**No.** The customer chooses an **Adapter**, never a Callback or a Decorator by name.

```text
Using CrewAI    →  use the CrewAI Adapter
Custom Agent     →  use the Generic Adapter
```

Which Integration Model that Adapter uses internally is an implementation detail the customer never has to think about — a direct continuation of the Public API philosophy in [`04-public-api.md`](./04-public-api.md).

---

## 10. Summary

```text
Agent Integration Models

Goal
ASES does not force a single integration mechanism onto every Agent.
Different frameworks expose different integration points; the SDK
supports multiple Integration Models, all converging on the same
ASES Observation output.

────────────────────────

Models
1. Callback Integration    (V1)
2. Middleware Integration    (V2)
3. Decorator Integration       (V2)
4. Manual API (Generic)           (V1 — fallback)

────────────────────────

Architecture Decision
The SDK Core is framework-agnostic.
Each framework is represented by its own independent Adapter.
Adding a new framework never requires modifying the Core.
```

The single most consequential sentence from this stage of the design:

> **ASES is not Framework-specific — it is Adapter-based.**

This is not a decision about CrewAI, or about LangGraph. It's a decision to build one stable Core, with every framework represented as a small Adapter layered on top of it — a decision that pays for itself every time a new framework needs to be supported.
