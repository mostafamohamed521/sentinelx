# 12 — Packaging & Distribution

> Source concept: Session 8 — the final design session, and deliberately a Product session rather than a technical one. Its question isn't "how do we build the SDK," but **"how does the customer actually get it, and get value from it, quickly?"**

---

## 1. Where Does the Customer Get ASES From?

Not GitHub cloning. Not copying files by hand. Certainly not requesting it by email.

> **ASES should feel like any respected Python library.**

```bash
pip install ases
```

That's the entire installation story.

---

## 2. Why PyPI, Specifically?

Because customers are already fluent in this exact pattern:

```bash
pip install fastapi
pip install requests
pip install crewai
```

ASES should offer the identical experience — this materially reduces friction, because there is nothing new to learn about *how* to install it, only *what* it does once installed.

---

## 3. The Shape of the Post-Install Experience

```text
1. pip install ases
2. from ases import configure
   configure(api_key="...")
3. from ases import monitor
4. Run the appropriate Adapter
```

No fifty-step onboarding — this is the direct, practical expression of the Public API philosophy established in [`04-public-api.md`](./04-public-api.md).

---

## 4. Where Does the API Key Live?

> **Not as a repeated argument.** `monitor(api_key="...")` is deliberately avoided, because the customer would end up repeating it everywhere.

Instead: **Configuration happens once**, and every subsequent component reads from it — the exact same architectural principle already established for Configuration as a shared Service in [`08-internal-architecture.md`](./08-internal-architecture.md), now applied at the packaging level too.

---

## 5. Supporting Multiple Environments

For customers who need Development, Staging, and Production distinctly, the answer is the Python-standard approach: `.env`.

```env
ASES_API_KEY=...
ASES_ENDPOINT=...
ASES_ENVIRONMENT=production
```

The exact contract for these variables is in [`contracts/environment-configuration.md`](./contracts/environment-configuration.md).

---

## 6. Does the Customer Need to Know the Endpoint?

**No.** A sensible default ships out of the box; a customer who genuinely needs to override it can — but the **Happy Path stays simple** by default. This mirrors the same instinct behind every other Public API decision in this layer: minimize what the customer has to think about.

---

## 7. How Should Documentation Begin?

Many projects open their documentation by explaining the architecture. That instinct is explicitly rejected here.

> **The first page of documentation is a Five-Minute Guide.**

```text
1. pip install ases
    ↓
2. Get an API Key
    ↓
3. configure()
    ↓
4. Add an Adapter
    ↓
5. Run the Agent
    ↓
Done
```

The measure of success: within five minutes, the customer sees their first real Observation. That single experience matters more than any amount of prose documentation.

Concretely, for a CrewAI Agent, steps 3-5 are three lines (see [`04-public-api.md §6a`](./04-public-api.md#6a-the-fast-path--monitor--configure--shutdown) for the full contract):

```python
from ases import configure, monitor
from ases.adapters import CrewAIAdapter

configure(api_key="ases_xxxxxxxxx")
monitor(CrewAIAdapter(crew))

crew.kickoff()
```

After the Five-Minute Guide, the documentation continues with **Getting Started**, then per-framework **Framework Guides** — CrewAI, then LangGraph, then the OpenAI Agents SDK — each with its own dedicated guide.

---

## 8. What Belongs in the README?

Kept deliberately short — not thousands of lines:

```text
What is ASES?
Installation
Quick Start
Supported Frameworks
Documentation
License
```

That's the entire scope. Nothing more belongs there.

---

## 9. What Do Examples Actually Look Like?

Not isolated code snippets — **complete, runnable projects**, one per framework:

```text
examples/
    crewai-basic/
    crewai-tools/
    langgraph-basic/
```

A customer should be able to run these directly, not just read them.

---

## 10. Versioning

> **Semantic Versioning**, applied in the standard way:

```text
1.0.0
1.1.0    — new capability added
1.1.1     — a bug fix
```

No custom versioning scheme is invented here — Semantic Versioning is a well-understood standard, and there's no reason to diverge from it.

**Breaking changes and deprecation.** The Public API surface this applies to is exactly the one [`contracts/public-api-contract.md §1`](./contracts/public-api-contract.md) names: `ASES`, `configure`, `monitor`, `shutdown`, and each Adapter's own public contract (e.g. `GenericAdapter.emit()`). Removing or changing the signature of any of these requires a major version bump, preceded by at least one minor version in which the old form still works but emits a `DeprecationWarning` naming its replacement. Internal paths (anything not listed in `public-api-contract.md §1`) carry no such guarantee and may change in any minor version, per section 1's own "internal paths are implementation detail" rule.

---

## 11. One Package, or Several?

The alternative considered was a plugin-style split — `ases-crewai`, `ases-langgraph`, and so on. After real deliberation, the decision:

> **A single package in V1.**

```bash
pip install ases
```

...contains every Adapter.

**Why:** the team building this is three developers. Splitting into multiple packages would double the maintenance burden for no real benefit at the current scale. Once the project genuinely outgrows a single package, splitting into `ases-core`, `ases-crewai`, `ases-langgraph`, and so on becomes worth revisiting — but not now. This is a clean, concrete example of a decision made specifically to avoid Over-Engineering — see [`ADR-007-single-package-distribution.md`](./adr/ADR-007-single-package-distribution.md).

---

## 12. Python Version Policy

```text
Supported: Python 3.11, 3.12, 3.13
Not supported: older versions
```

**Why:** this keeps the codebase clean, and avoids carrying compatibility shims for versions the ecosystem itself is moving away from.

---

## 13. Dependency Policy

One governing rule:

> **Every dependency must have a clear, articulable reason for existing.**

If a library exists purely to save ten lines of code, those ten lines get written directly instead. New dependencies are not added casually — this discipline is what keeps ASES lightweight over its lifetime, not just at launch.

---

## 14. The Full Customer Journey, Restated in Under a Minute

```text
Developer
    ↓
pip install ases
    ↓
Get API Key
    ↓
configure()
    ↓
Enable Framework Adapter
    ↓
Run Agent
    ↓
ASES Collects Events
    ↓
Observation Built
    ↓
SentinelX Receives Observation
    ↓
Dashboard Shows Result
```

This is the first point in the entire design process where the whole customer journey can genuinely be told in under a minute — a meaningful signal that the underlying design has converged correctly.

In code, "Enable Framework Adapter" through "Run Agent" is:

```python
from ases import configure, monitor
from ases.adapters import CrewAIAdapter

configure(api_key="ases_xxxxxxxxx")
monitor(CrewAIAdapter(crew))

crew.kickoff()
```

---

## 15. Summary

```text
Packaging & Distribution

Distribution
ASES is distributed as a standard Python package through PyPI.
Installation: pip install ases

────────────────────────

Configuration
Initialized once: configure(api_key="...")
SDK components consume shared configuration internally.

────────────────────────

Environment Variables
- ASES_API_KEY
- ASES_ENDPOINT
- ASES_ENVIRONMENT

────────────────────────

Documentation Structure
1. Five Minutes Guide
2. Getting Started
3. Framework Guides
4. SDK API Reference
5. Architecture

────────────────────────

Examples
examples/
- crewai-basic/
- crewai-tools/
- langgraph-basic/

────────────────────────

Versioning
Semantic Versioning — Major, Minor, Patch

────────────────────────

Package Strategy
Single package in V1.
No package splitting until a real maintenance need appears.

────────────────────────

Supported Python Versions
Python 3.11, 3.12, 3.13

────────────────────────

Dependency Policy
Every dependency must have a clear architectural reason.
Avoid unnecessary third-party libraries.
```

---

## 16. Closing the Design Phase

Looking back across this entire series, a full product came out of it — not merely an SDK. It spans the Integration philosophy, the Integration Models, the Public API, the Customer Journey, the Framework strategy, the Internal Architecture, the Observation Lifecycle, the Transport Layer, the Repository structure, and Packaging & Distribution.

No single decision here was made because it "looked professional." Every one of them was checked against the same three standing rules, present from the very first session:

```text
1. Production-ready.
2. Simple enough for a small team to build and maintain.
3. Extensible without requiring a redesign.
```

With this session complete, the next step is not more design — it's [`13-implementation-roadmap.md`](./13-implementation-roadmap.md), which turns everything above into an actual build order.
