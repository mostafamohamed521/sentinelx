# 02 — Integration Philosophy

> This document answers the question that has to be settled before any mechanism (callback, middleware, decorator) is chosen: **how do we approach reaching into a running Agent at all?**

---

## 1. The Starting Fact: Not Every Agent Is Built the Same Way

Before choosing any integration mechanism, one fact has to be accepted: AI Agents are not built uniformly. Picture four companies:

```text
Company A  →  uses CrewAI
Company B  →  uses LangGraph
Company C  →  wrote their Agent from scratch
Company D  →  uses the OpenAI Agents SDK
```

There is no single line of code that could be handed to all four of them. This is the first architectural fact the Integration Layer has to be built around:

> **We do not impose one Integration method.**

---

## 2. The USB Analogy

The right mental model here is a USB port. USB does not force every device into an identical physical shape — it defines a **uniform interface contract** that different devices satisfy in their own way.

The Integration Layer follows the same idea: it allows **more than one Integration Model** (see [`03-agent-integration-models.md`](./03-agent-integration-models.md)), but every one of them must converge on producing the exact same output — a single, canonical Observation. The mechanism varies; the result never does.

---

## 3. We Do Not Break In — We Use the Official Door

The second pillar of this philosophy, made explicit once the concept of an **Integration Point** is examined directly (see [`06-integration-point-concept.md`](./06-integration-point-concept.md)), is this:

> **We never "spy" on an Agent. We only integrate through officially supported extension points.**

If a framework offers no such door — no callback, no hook, no middleware, nothing — the honest answer is that this layer **cannot** integrate with it. Not this version, not through a workaround. This layer explicitly rejects monkey-patching, reflection tricks, or any other means of forcing visibility into a system that hasn't offered it. That restraint is not a limitation to apologize for — it is what makes this a **Production Layer** rather than an experimentation tool.

---

## 4. Restating the Core Thesis From This Angle

The governing sentence established in [`01-overview.md`](./01-overview.md) is really an integration-philosophy statement as much as it is a product-definition one:

> **We do not monitor the Agent. We standardize the events the Agent or its Framework allows us to see, then transform them into a single Observation and send it to SentinelX.**

Every subsequent design decision in this layer — the existence of Adapters, the Thin Adapter principle, the Canonical Event Model, the refusal to build a universal monitor — is a direct structural consequence of accepting this sentence as true before writing a single line of code.

---

## 5. What This Philosophy Rules Out, Explicitly

```text
✘ A single, one-size-fits-all integration mechanism for every framework.
✘ Monkey-patching a framework that offers no extension point.
✘ Reflection-based introspection to "find" data the framework didn't expose.
✘ Treating "we can watch everything" as a marketing claim.
```

## 6. What This Philosophy Enables

```text
✔ Multiple Integration Models, chosen per framework, converging on one Observation format.
✔ An honest, accurate claim: "ASES integrates with any Python-based AI Agent that exposes
  an integration point (callbacks, middleware, hooks, or custom events)."
✔ A Core that never has to change shape to accommodate a new framework — only the
  Adapter layer grows.
```

This is the philosophical foundation the next two documents build directly on top of: [`03-agent-integration-models.md`](./03-agent-integration-models.md) turns "we allow multiple integration models" into a concrete, prioritized list, and [`06-integration-point-concept.md`](./06-integration-point-concept.md) turns "we use the official door" into a precise, mechanism-by-mechanism definition.
