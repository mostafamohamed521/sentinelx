# ADR-006: The Repository Is Organized by Domain, Not by Technical Layer or by Framework

| | |
|---|---|
| **Status** | ✅ Accepted (Frozen) |
| **Source** | Session 7.5 — Repository & Project Architecture |
| **Affects** | The entire package layout, the public/internal boundary via `__init__.py`, and how future frameworks are added |

---

## Context

Three common conventions exist for organizing a Python SDK's codebase: by technical layer (`builder/`, `collector/`, `transport/`), by supported framework (`crewai/`, `langgraph/`), or by business domain. A decision was needed before any implementation began, since restructuring a codebase after the fact is expensive and error-prone.

---

## Decision

**The repository is organized by domain** — six packages, each representing a coherent idea rather than a technical concern or a specific framework:

```text
ases/
├── adapters/       — one folder per framework, each with a single adapter.py entry point
├── observation/    — Collector, Builder, Validator, Models — together
├── pipeline/       — event routing (Dispatcher, Events)
├── transport/       — Queue, Worker, Serializer, Client
├── config/            — the sole reader of environment configuration
└── shared/              — Logger, Exceptions, Constants only — never a generic "utils"
```

The Public API is exposed exclusively through the package root (`__init__.py`), which draws the explicit line between what is Public and what is Internal.

---

## Rationale

### Why Not Organize by Technical Layer?
A layer-based structure (`builder/`, `collector/`, `transport/`, `validator/` as siblings) makes the codebase read as a stack of generic layers rather than as a coherent product — and it separates components (like the Collector and Builder) that are conceptually inseparable and change together far more often than they change independently.

### Why Not Organize by Framework?
A framework-based structure (`crewai/`, `langgraph/`, `autogen/` as top-level siblings, each containing its own full pipeline) would cause every framework folder to duplicate the same Observation, Pipeline, and Transport logic — directly undermining the Core/Adapter separation established in [`ADR-001-adapter-based-framework-strategy.md`](./ADR-001-adapter-based-framework-strategy.md).

### Why Domain-Based Organization Wins
Grouping by domain keeps everything related to one idea together — the entire Observation lifecycle (Collector, Builder, Validator, Models) lives in one package, the entire network concern lives in another. This directly mirrors the internal architecture in [`08-internal-architecture.md`](../08-internal-architecture.md), so the file layout and the mental model of the system stay in sync.

### Why the Public API Is Root-Level Only
Requiring customers to import from deep internal paths (`ases.pipeline.sdk.transport.dispatcher.monitor`) both looks unprofessional and, more importantly, permanently couples customer code to internal structure. Restricting the Public API to root-level names (`from ases import monitor`) means the internal architecture can be freely refactored later without ever breaking a customer's existing code — a structural guarantee, not a documentation promise.

### Why Adapters Get Exactly One Entry Point Each
The rejected alternative — a `callback.py`, `wrapper.py`, `middleware.py`, and `decorator.py` per framework folder — would repeat the same four-file pattern for every single framework added. A single `adapter.py` per framework keeps the pattern for adding a new framework mechanical: one new folder, one new file.

---

## Rejected Alternatives

| Alternative | Reason for Rejection |
|-------------|------------------------|
| Organize by technical layer (`builder/`, `collector/`, `transport/` as top-level siblings) | Reads as a stack of layers, not a product; separates components that change together |
| Organize by framework (`crewai/`, `langgraph/` each containing a full pipeline) | Duplicates Core logic per framework, directly contradicting the Adapter-based strategy |
| A generic `utils/` package for shared code | Reliably degrades into an unstructured dump over time — replaced with a narrowly scoped `shared/` containing only Logger, Exceptions, and Constants |
| Expose internal module paths for customer imports | Permanently couples customer code to internal structure, preventing safe future refactors |
| A `cli/` package, built preemptively | No real use case exists yet — building it now would be speculative, unjustified scope |

---

## Consequences

- ✅ Adding a new framework is a well-defined, mechanical task: one new folder under `adapters/`, containing one `adapter.py`.
- ✅ The internal architecture can evolve freely without breaking customer-facing imports, because the Public API surface is structurally isolated at the package root.
- ✅ Tests mirror this same domain structure (`tests/adapters/`, `tests/observation/`, ...), so locating a test for any given piece of code is immediate and unambiguous.
- ⚠️ Discipline is required to keep `shared/` from becoming a de facto `utils/` package over time — any addition to it should be checked against the explicit "Logger, Exceptions, Constants only" scope.
