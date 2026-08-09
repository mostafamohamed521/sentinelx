# 11 — Repository Architecture

> Source concept: Session 7.5 — the last real architecture session before implementation. Every prior document defined Components. None of them ever asked where those components actually live in the codebase, and that answer is the difference between a repository that stays professional and one that becomes unreadable within six months.

---

## 1. Three Schools of Thought for Organizing the Codebase

### School 1 — Organize by Technical Layer
```text
builder/
collector/
transport/
validator/
```
The most common approach — and the one rejected here, because it makes the SDK read as a stack of layers rather than as a coherent product.

### School 2 — Organize by Framework
```text
crewai/
langgraph/
autogen/
```
Rejected even more firmly — every framework folder would end up duplicating large amounts of shared logic, directly undermining the Core/Adapter separation this entire design has been built around.

### School 3 — Organize by Domain
```text
Each package represents an idea, not a layer.
```

**This is the school adopted.** It raises one further question: what are this project's actual Domains?

---

## 2. The Six Domains

### Domain 1 — `adapters/`
Any new framework enters here.

```text
adapters/
    crewai/
    langgraph/
    base.py
```

Note: the Core is deliberately **not** located inside this package.

### Domain 2 — `observation/`
The largest package, because it is the heart of the SDK.

```text
observation/
    collector.py
    builder.py
    validator.py
    models.py
```

Keeping the Collector, Builder, Validator, and data Models together in one place is considered better than scattering the Collector far away from the Builder it feeds — everything about the Observation lives in exactly one location.

### Domain 3 — `pipeline/`
Responsible for Event routing.

```text
pipeline/
    dispatcher.py
    events.py
```

Small, but its existence matters — this is the framework-agnostic entry point every Adapter's output funnels through.

### Domain 4 — `transport/`
An independent package, matching the internal decomposition from [`10-transport-layer.md`](./10-transport-layer.md).

```text
transport/
    queue.py
    worker.py
    serializer.py
    client.py
```

Everything network-related lives in one place.

### Domain 5 — `config/`
> **Individual classes reading environment variables directly is explicitly rejected.**

```text
config/
    settings.py
```

Exactly one file reads `.env` — everything else consumes configuration through it, never independently.

### Domain 6 — `shared/`
The smallest package — deliberately **not** called `utils/`.

> **A package named `utils` is disliked here on principle, because it inevitably turns into a dumping ground.**

```text
shared/
    logger.py
    constants.py
    exceptions.py
```

Nothing more is allowed to accumulate here.

---

## 3. Where Does the Public API Live?

At the **root** of the package — deliberately.

```python
from ases import monitor
```

never:

```python
from ases.pipeline.sdk.transport.dispatcher.monitor   # never this
```

The customer should never see anything below the surface. `__init__.py` at the root is what decides exactly what is Public versus what remains Internal:

```python
from ases import monitor
from ases import configure
from ases import shutdown
```

```python
from ases import ASES
```

is also root-level and public — `monitor`/`configure`/`shutdown` are thin convenience wrappers over this class, not a separate surface (see [`04-public-api.md §6a`](./04-public-api.md#6a-the-fast-path--monitor--configure--shutdown) for the full reconciliation). While something like:

```python
from ases.transport.worker    # forbidden for customer use
```

is off-limits. This is what allows the internal Architecture to change freely in the future without ever breaking a customer's code — a direct, structural guarantee, not a policy that relies on customer discipline.

---

## 4. Where Do Tests Live?

**Not inside the packages themselves.**

```text
tests/
    adapters/
    observation/
    transport/
    pipeline/
```

Mirroring the project's own structure means any test's corresponding source code is trivial to locate.

---

## 5. Where Do Examples Live?

An easy thing to forget, and treated here as mandatory:

```text
examples/
    crewai/
    langgraph/
```

> **This will be the very first place a customer opens — not the documentation.**

---

## 6. Where Does Documentation Live?

```text
docs/
    getting-started.md
    architecture.md
    api.md
    frameworks/
```

This is what makes the project read as a credible open-source project, not just a working one.

---

## 7. Is There a CLI?

> **No — and this absence is itself a deliberate decision.**

There is currently no real use case that requires one. No folder named `cli/` exists until a genuine need for it appears — a direct, concrete instance of avoiding Over-Engineering.

---

## 8. The Complete Package Structure

```text
ases/
│
├── adapters/
│      ├── crewai/
│      ├── langgraph/
│      └── base.py
│
├── observation/
│      ├── collector.py
│      ├── builder.py
│      ├── validator.py
│      └── models.py
│
├── pipeline/
│      ├── dispatcher.py
│      └── events.py
│
├── transport/
│      ├── queue.py
│      ├── worker.py
│      ├── serializer.py
│      └── client.py
│
├── config/
│      └── settings.py
│
├── shared/
│      ├── logger.py
│      ├── constants.py
│      └── exceptions.py
│
├── __init__.py
└── py.typed
```

And outside the package itself:

```text
project/
│
├── ases/
├── tests/
├── examples/
├── docs/
├── pyproject.toml
├── README.md
├── LICENSE
└── .env.example
```

See [`diagrams/architecture/repository-structure.svg`](./diagrams/architecture) for the full visual tree.

---

## 9. One More Refinement: How Adapters Are Organized Internally

Each Adapter will eventually need some combination of a Hook, a Callback, a Decorator, or a Wrapper (see [`03-agent-integration-models.md`](./03-agent-integration-models.md) and [`06-integration-point-concept.md`](./06-integration-point-concept.md)). The rejected approach:

```text
crewai/
    callback.py
    wrapper.py
    middleware.py
    decorator.py
```

Rejected because this pattern would repeat itself, near-identically, for every single framework added.

### The Adopted Pattern

> **Every Adapter exposes exactly one entry point.**

```text
crewai/
    adapter.py
```

Everything specific to that framework's integration mechanism lives inside that one file. The payoff: adding a new framework becomes a mechanical, well-defined action — create a new folder, add one `adapter.py` — which makes the process of supporting a new framework genuinely simple, not just simple in theory.

---

## 10. Summary

```text
Repository & Project Architecture

Design Philosophy
Organize the SDK by business domains, not technical layers.

────────────────────────

Package Structure
ases/
- adapters/
- observation/
- pipeline/
- transport/
- config/
- shared/

────────────────────────

Public API
Expose only the stable surface at the package root:
- ASES (class — full control)
- monitor() / configure() / shutdown() (thin wrappers over ASES — fast path)
Everything else remains internal.

────────────────────────

Tests
Mirror the package structure.
tests/
- adapters/
- observation/
- transport/
- pipeline/

────────────────────────

Examples
examples/
- crewai/
- langgraph/

────────────────────────

Documentation
docs/
- getting-started.md
- architecture.md
- api.md
- frameworks/

────────────────────────

Adapter Design
Each framework exposes a single entry point: adapter.py
Internal implementation remains hidden.

────────────────────────

Design Principles
- Domain-driven package organization.
- Thin public API.
- Internal modules are private.
- No generic `utils` package.
- No CLI until a real use case exists.
```

---

## 11. Why This Session Mattered as Much as Any Architecture Session

Reviewing the full design from the very first session to this point reveals a build order that runs deliberately from the inside out:

```text
Product philosophy
    ↓
Customer journey
    ↓
Architecture
    ↓
Runtime behavior
    ↓
Repository structure
```

This is the reverse of how most projects actually unfold — where files get written first, and the architecture is discovered, painfully, afterward. The practical result: implementation can begin with every file's existence already justified before it is created — arguably the single largest success of this entire design phase.
