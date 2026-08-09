# 06 — What Exactly Is an Integration Point?

> Source concept: Session 4.6. Every prior document uses the phrase "Integration Point" as if its meaning were obvious. This document makes it precise, with concrete mechanisms and a firm, honest boundary.

---

## 1. A Story Before Any Technical Term

Imagine ASES as a new employee at a company. On their first day, they announce: *"I'm going to monitor every AI Agent in this building."* The CTO asks, reasonably: *"Okay — how, exactly?"* If the honest answer is *"I'll just stand next to them,"* that answer collapses immediately:

```text
If the Agent is behind a locked door       → it can't be seen.
If it closes the door every time it acts    → it can't be seen.
If there's no key at all                     → it can't be seen.
```

**Monitoring cannot happen without a door to enter through.** This is the first rule of this entire document.

---

## 2. Definition

> **An Integration Point is the official extension mechanism a framework or application exposes, allowing an outside system to observe execution without modifying its business logic.**

A restaurant analogy makes this concrete: a quality inspector doesn't kick down the kitchen door — they use the staff entrance, because the restaurant itself designated that as the door for exactly this purpose. ASES behaves the same way:

> **This layer does not spy. It uses officially supported Extension Points only.**

---

## 3. The Four Concrete Shapes an Integration Point Takes

### 3.1 Callback
The Agent itself calls out and reports: *"I just finished this step."*

```text
Agent
    ↓
Tool Started
    ↓
"Hey ASES — I started a Tool."
    ↓
ASES records the Event
    ↓
Agent continues
```

The Agent initiates contact — ASES never has to reach in on its own.

### 3.2 Hook
Slightly different: before a step happens, execution pauses briefly and hands control outward, like a security guard who logs a badge before letting someone through a door.

```text
Before Tool execution
    ↓
Hook
    ↓
ASES
    ↓
Tool executes
```

The distinguishing feature: presence **before** the event, not after it.

### 3.3 Middleware
Picture a highway tollbooth: every car passing through can be photographed, timed, and logged before the gate opens.

```text
Agent → Middleware → LLM
```

Every Prompt and every Response passes through this single checkpoint.

### 3.4 Wrapper
Perhaps the cleverest of the four. Imagine wrapping a gift: the gift itself is unchanged, but anyone opening it must pass through the wrapping first.

```python
ases.wrap(agent)
```

instead of calling `agent.run()` directly. From that point on, ASES can observe everything passing through, without altering the Agent underneath.

---

## 4. Why Different Frameworks Choose Different Shapes

Each framework was built differently — one favors Callbacks, another favors Middleware, a third exposes Hooks. This is not a problem to solve; it's exactly why the **Adapter** exists. Each framework's specific integration mechanism is the Adapter's concern alone — the Core never has to know or care which of the four shapes a given framework happens to use.

---

## 5. The Harder Question: What If There Is No Door At All?

Consider an Agent written like this:

```python
while True:
    do_everything()
```

No Callback. No Hook. No Middleware. No Wrapper. Nothing.

**The honest answer: this cannot be integrated.**

> **ASES cannot monitor what the system does not allow it to see.**

This is not a limitation of ASES specifically — it is a fact of software engineering. And the response to this gap is deliberately **not** any of the following:

```text
✘ Breaking into the runtime.
✘ Monkey-patching.
✘ Reflection-based introspection.
```

Because this is a Production product, not an experimentation tool — a principle already established in [`02-integration-philosophy.md`](./02-integration-philosophy.md), now applied to its hardest edge case.

---

## 6. Applying This Directly to Claude Code

```text
Does Claude Code expose an Integration Point?        No.
Is there source code access?                           No.
Is there an official SDK into its Runtime?               No.
```

**Conclusion: ASES V1 cannot monitor it.** This is not a gap in the project's ambition — it is the honest difference between **Product Scope** and **Unlimited Dreams**. See [`07-agent-framework-ecosystem.md`](./07-agent-framework-ecosystem.md) for where this realization leads for the product roadmap.

---

## 7. What If a Company Wrote Its Agent in Python From Scratch?

This is a genuinely different situation — they own the code. They can add a single point of contact: a call after each Tool runs, or a call into the Generic Adapter (see [`03-agent-integration-models.md`](./03-agent-integration-models.md)). This is exactly why the Manual API fallback exists.

The deeper realization this produces:

> **This layer does not need every framework to support ASES specifically. It only needs one thing: a single place to receive Events from** — whether that's a Callback, a Hook, Middleware, or even a single function call.

If a door exists, integration is possible.

---

## 8. The Decision That Reshapes the Adapter's Job Description

> **An Adapter's real job is not "knowing a framework." Its real job is locating that framework's Integration Point, then converting whatever comes out of it into standard ASES Events.**

The practical consequence: if a new framework called `SuperAgentAI` appeared tomorrow, this project would never need to rewrite the SDK. It would ask exactly one question:

> **Where is the Integration Point?**

If the answer exists, a small Adapter gets written, and the Core runs unchanged — see [`ADR-001-adapter-based-framework-strategy.md`](./adr/ADR-001-adapter-based-framework-strategy.md).

---

## 9. Summary

```text
Integration Point Definition

An Integration Point is the official extension mechanism exposed by
an AI framework or application that allows third-party systems to
observe execution without modifying business logic.

ASES never "spies" on an Agent.
It only integrates through officially supported extension points.

────────────────────────

Common Integration Point Shapes

Callback     — Framework notifies ASES after/before specific events.
Hook          — Framework pauses execution, allowing external code to run.
Middleware     — All requests and responses pass through a shared layer.
Wrapper          — ASES wraps an existing object without changing its behavior.

────────────────────────

Key Principle

If there is an Integration Point... ASES can integrate.
If there is no Integration Point... ASES cannot monitor that Agent.

────────────────────────

Architecture Decision

An Adapter's responsibility is not to "support a framework."
Its responsibility is to locate the framework's Integration Point and
convert whatever it emits into standard ASES Events.
```
