# ADR-004: Business Logic Lives in the Application Layer, Implemented as Actions — Not Services

| | |
|---|---|
| **Status** | ✅ Accepted (Documentation Baseline v2.0) |
| **Conflict Source** | Cross-Review, Conflict 4 |
| **Affects** | The Implementation Layers design (Session 6), and every module's internal structure |

---

## Context

Documentation Baseline v1.0's `BACKEND_ARCHITECTURE.md` states explicitly: *"Business logic belongs inside Services rather than Controllers,"* and names its components accordingly (`Observation Service`, `Prediction Service`, `Alert Service`, `Dashboard Service`).

The Backend Architecture sessions, working at the implementation-layer level, deliberately moved away from "Service" as the unit of business logic, adopting "Action" instead, with one Action per use case, living in an `Application` layer.

---

## Decision

**The newer Action-based architecture is adopted.** The v1.0 wording is understood as conceptual, pre-implementation language, not a binding technical convention. Business Logic lives in the **Application Layer**, and is implemented using **Actions** — one Action per use case (e.g., `ReceiveObservationAction`, `CreateAgentAction`, `GenerateApiKeyAction`, `ResolveAlertAction`).

---

## Rationale

### The v1.0 Language Was Conceptual, Not Prescriptive
At the time `BACKEND_ARCHITECTURE.md` was written, the team was thinking conceptually — high level Business Flow, not concrete implementation. The word "Service" was a reasonable stand-in at that stage, before the Implementation Layers were designed in detail.

### "Service" Is Not Wrong — It's Just Too Elastic
The word `Service` tends to inflate over time. In practice, an `ObservationService` grows to encompass validation, persistence, event dispatching, and more — often approaching a thousand lines doing 25 different things. This is a well-known failure mode in Laravel projects specifically.

### Actions Enforce Single Responsibility Naturally
`ReceiveObservationAction`, `ValidateObservationAction`, and `StoreObservationAction` each do exactly one thing. This achieves Single Responsibility as a natural consequence of the naming convention itself, rather than requiring ongoing developer discipline to maintain it.

---

## Rejected Alternatives

| Alternative | Reason for Rejection |
|-------------|------------------------|
| Keep `Service` classes as originally specified | Directly reproduces the well-known "God Service" failure mode this decision is specifically meant to prevent |
| Introduce both `Service` and `Action` as separate concepts (Services orchestrate, Actions execute) | Adds a distinction with no clear boundary in practice, and reintroduces exactly the ambiguity Actions were meant to eliminate |

---

## Consequences

- ✅ Every unit of business logic maps to exactly one use case — easy to locate, easy to test in isolation.
- ✅ No single class accumulates unrelated responsibilities over time.
- ✅ Aligns with the Modular Monolith's overall philosophy: Production-Level, without Over Engineering.
- ⚠️ `docs.zip`'s `BACKEND_ARCHITECTURE.md` still contains the literal sentence *"Business logic belongs inside Services rather than Controllers"* — this line is now superseded and should be corrected in a follow-up sync pass to read: *"Business Logic lives in the Application Layer and is implemented using Actions."*
