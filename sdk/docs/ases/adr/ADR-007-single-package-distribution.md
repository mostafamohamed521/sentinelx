# ADR-007: ASES Ships as a Single PyPI Package in V1 — No Plugin Split

| | |
|---|---|
| **Status** | ✅ Accepted (Frozen) |
| **Source** | Session 8 — Packaging & Distribution |
| **Affects** | The PyPI packaging strategy, dependency management, and the maintenance burden on the current team |

---

## Context

With multiple framework Adapters planned (CrewAI, LangGraph, the OpenAI Agents SDK, and others over time — see [`07-agent-framework-ecosystem.md`](../07-agent-framework-ecosystem.md)), a decision was needed on packaging strategy: ship one package containing everything, or split into a plugin-style family of packages (`ases-core`, `ases-crewai`, `ases-langgraph`, ...).

---

## Decision

**A single package, `ases`, ships in V1**, containing the Core and every supported Adapter together.

```bash
pip install ases
```

The plugin-split model is explicitly deferred, to be reconsidered only if a genuine, demonstrated maintenance need for it emerges later.

---

## Rationale

### Team Size Is a Real Constraint, Not a Detail
The team building and maintaining this SDK is three developers. A multi-package split multiplies release coordination, versioning, and dependency management overhead — real, ongoing costs — for a benefit (independent per-framework installation/versioning) that has no current customer demand behind it.

### This Mirrors the Project's Standing Discipline Against Over-Engineering
The same reasoning that kept Middleware and Decorator integration out of V1 (see [`03-agent-integration-models.md`](../03-agent-integration-models.md)) and kept a CLI out of the repository entirely (see [`ADR-006-domain-driven-repository-structure.md`](./ADR-006-domain-driven-repository-structure.md)) applies here: build what's needed now, expand when a real need appears.

### The Domain-Driven Repository Structure Already Makes a Future Split Cheap
Because Adapters already live in clearly separated `adapters/<framework>/` folders with a single entry point each (see [`ADR-006-domain-driven-repository-structure.md`](./ADR-006-domain-driven-repository-structure.md)), splitting into `ases-core`, `ases-crewai`, `ases-langgraph`, etc. later remains a mechanical, low-risk refactor if and when it becomes worthwhile — this decision doesn't foreclose that option, it simply doesn't pay its cost prematurely.

---

## Rejected Alternatives

| Alternative | Reason for Rejection |
|-------------|------------------------|
| Ship `ases-core` plus a separate package per framework from V1 | Doubles release and maintenance overhead for a three-person team, with no current customer requirement driving it |
| Ship a package per framework only, with no shared core package | Would either duplicate Core logic per package or create tight inter-package coupling — worse than either alternative considered |

---

## Consequences

- ✅ One release process, one version number, one set of release notes to maintain in V1.
- ✅ A customer installs one thing and gets every supported Adapter, reducing their own dependency management burden too.
- ✅ The path to a future plugin-style split remains open and low-cost, because the domain-driven internal structure already anticipates it.
- ⚠️ Every Adapter's dependencies (e.g., the CrewAI package itself, if required at import time) become part of the single `ases` package's own dependency surface — this must be weighed carefully per the Dependency Policy in [`12-packaging-and-distribution.md`](../12-packaging-and-distribution.md) as more Adapters are added, to avoid the single package becoming heavy over time.
