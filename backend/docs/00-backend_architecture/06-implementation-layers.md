# 06 — Implementation Layers

> Source: Session 6 — Implementation Layers
> Resolved per [`adr/ADR-004-actions-over-services.md`](./adr/ADR-004-actions-over-services.md): Business Logic lives in the **Application Layer**, implemented using **Actions** — not "Services." The layer count is finalized at **5**, with Persistence folded into Infrastructure.

---

## 1. Module vs. Layer

`Module` answers: *"Who owns this business capability?"*
`Layer` answers: *"How is this capability implemented?"*

Module is responsibility. Layer is implementation technique.

---

## 2. The Golden Rule

> **Every module in SentinelX must share the exact same internal structure.**

`Authentication`, `Agent`, `Observation`, and `Alert` all look different from the outside — but identical from the inside. This is what keeps the project coherent a year into development.

---

## 3. What Are We Actually Building?

A **Laravel Modular Monolith** — not Microservices, not academic Clean Architecture, not heavyweight DDD. Production-level, but simple.

---

## 4. The Five Layers (Final, Resolved)

The original design proposed six layers (API, Application, Domain, Infrastructure, Persistence, Presentation). During the same session, Persistence was folded into Infrastructure — since the database is, from the Domain's point of view, just another external system to talk to — reducing folder count without losing any clarity, and aligning better with Laravel's own conventions. This is the version adopted as final:

```text
API
    ↓
Application
    ↓
Domain
    ↓
Infrastructure
    ├── Persistence (Repositories, Models)
    ├── ML Client
    ├── Cache
    ├── Queue
    └── Storage
    ↓
Presentation
```

---

### Layer 1 — API

The gateway.

```text
HTTP Request
    ↓
Route
    ↓
Controller
    ↓
Form Request
```

**Sole responsibility:** receive the request, do initial validation, call the appropriate Action, return a response. Nothing more.

**Forbidden here:** Business Logic, Database access, ML calls, raw Queries.

---

### Layer 2 — Application

The most important layer — the heart of every module.

This is where **Actions** live. See [`adr/ADR-004-actions-over-services.md`](./adr/ADR-004-actions-over-services.md) for why "Actions" was chosen over "Services." Examples:

```text
CreateAgentAction
GenerateApiKeyAction
ReceiveObservationAction
ResolveAlertAction
```

Each Action does exactly one thing.

**Responsible for:**
```text
Executing the business flow.
Coordinating between layers.
Calling the Domain.
Calling Infrastructure when needed.
```

---

### Layer 3 — Domain

Where most people get this layer wrong. This is not heavyweight DDD — it's simply the home of **Business Rules**:

```text
Is deleting this Agent allowed?
Has this API Key expired?
Is this Observation valid?
Can this Alert transition to Resolved?
```

**Contains no:** Laravel, Eloquent, or HTTP concepts of any kind.

---

### Layer 4 — Infrastructure

Everything about the outside world:

```text
Database (Persistence: Repositories, Models)
Cache
Queue
Mail
ML Client
Storage
```

Anything outside the system's own logic belongs here. Instead of an Action talking to FastAPI directly, it talks to an `MLClient` living in this layer.

---

### Layer 5 — Presentation

Output shaping.

```text
API Resources
DTOs
Transformers
```

**Responsible for:** turning internal data into the correct JSON shape — instead of the Controller building the response by hand. If a GraphQL or CLI interface is added later, both can reuse the exact same Application layer.

---

## 5. What Every Module Looks Like

```text
Module
├── API
├── Application
├── Domain
├── Infrastructure   (includes Persistence)
└── Presentation
```

A repeated template.

### Example — Observation Module
```text
Observation
├── API
│   ├── Controllers
│   ├── Requests
│   └── Routes
│
├── Application
│   ├── ReceiveObservationAction
│   ├── ValidateObservationAction
│   └── StoreObservationAction
│
├── Domain
│   ├── Observation
│   ├── ObservationValidator
│   └── ObservationPolicy
│
├── Infrastructure
│   ├── EventNormalizer
│   └── Persistence
│       ├── ObservationRepository
│       └── ObservationModel
│
└── Presentation
    └── ObservationResource
```

### Example — Analysis Module
```text
Analysis
├── API
│
├── Application
│   ├── AnalyzeObservationAction
│   └── StorePredictionAction
│
├── Domain
│   ├── Prediction
│   └── RiskAssessment
│
├── Infrastructure
│   ├── MLClient
│   └── Persistence
│       ├── PredictionRepository
│       └── EvidenceRepository
│
└── Presentation
```

Note: the `MLClient` lives in Infrastructure — never in Application or Domain.

---

## 6. Why Actions Instead of Services?

`Service` is a word that inflates over time. An `ObservationService` tends to grow to a thousand lines doing 25 different things. `ReceiveObservationAction`, by contrast, does exactly one thing.

```text
One Action = One Use Case.
```

This achieves Single Responsibility naturally, without discipline needing to be enforced after the fact.

---

## 7. Why a Repository?

Because every module owns its own data (see [`04-module-responsibilities.md`](./04-module-responsibilities.md)), and data access should flow through exactly one clear Repository per entity.

---

## 8. Why Is Presentation Separate?

Because **JSON shape is not Business Logic**. If a GraphQL API or a CLI is added tomorrow, both can reuse the exact same Application layer — only Presentation would need to differ.

---

## 9. The Final Rules

```text
API
    ↓
Application
    ↓
Domain
    ↓
Infrastructure   (Persistence lives here)
```

And when needed:

```text
Application
    ↓
Infrastructure
```

**Forbidden:**
```text
API → Repository directly
Controller → Model directly
Controller → ML Client directly
```

Everything must pass through an Action.

---

## 10. The Complete Shape

```text
API
      │
      ▼
Application
╱         ╲
▼           ▼
Domain     Infrastructure (incl. Persistence)
      │
      ▼
Presentation
```

---

## 11. Session 6 Summary (Resolved)

```text
Implementation Layers

API
────────────────────
✔ Routes
✔ Controllers
✔ Requests

Application
────────────────────
✔ Actions
✔ Business Flows

Domain
────────────────────
✔ Business Rules
✔ Policies
✔ Validation Logic

Infrastructure
────────────────────
✔ Repositories & Models (Persistence)
✔ ML Client
✔ Cache
✔ Queue
✔ External Services

Presentation
────────────────────
✔ API Resources
✔ Response DTOs

────────────────────

Architecture Rules

✔ Every module has the same internal structure.
✔ Controllers are thin.
✔ One Action = One Use Case.
✔ Business rules belong to Domain.
✔ External integrations, including the database, belong to Infrastructure.
✔ Responses belong to Presentation.
```

---

## 12. Why This Final Shape Was Chosen

Folding Persistence into Infrastructure was adopted for two reasons: it reduces folder count without losing any clarity, and it aligns more naturally with Laravel's own conventions while still preserving the separation between Business Logic and the technical means of storing it. This is not a design compromise — it's a simplification of the same idea, in keeping with the project's founding principle: **Production-Level, without Over Engineering.**
