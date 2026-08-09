# 05 — Customer Integration Journey

> Source concept: the realization that led to Session 4.5. This document exists because of a mistake this project caught in itself before it became expensive: designing `attach()` and `start()` before answering a far more fundamental question first.

---

## 1. The Mistake This Document Corrects

Design work had already moved into `attach()`, `start()`, and Adapters (see [`04-public-api.md`](./04-public-api.md)) before a more basic question was ever asked directly:

> **How does the customer actually come to use ASES in the first place?**

That question should have come first. This document rebuilds the story from the beginning — deliberately from the customer's point of view, not the code's.

---

## 2. The Discovery That Reframed the Entire Product

Walking through the customer's story from the very start surfaces an assumption that had been made silently: that the customer *has source code* to integrate into. That assumption is not always true.

```text
Does the owner of Claude Code have source code access to modify it?    No.
Does the owner of Cursor?                                                No.
Does the owner of a ChatGPT Agent / OpenAI Operator session?               No.
```

If a customer has no source code, `attach()` simply cannot work — there is nothing to attach to. This exposes two fundamentally different customer types:

```text
Type 1 — owns/builds the Agent, has source code
             → can genuinely use an SDK.

Type 2 — uses a pre-built Agent application (Claude Code, Cursor, ChatGPT, ...)
             → cannot add an SDK — there is no code to add it to.
```

---

## 3. Two Different Markets, Not One

This layer had been unintentionally blending two distinct markets:

```text
Market 1 — Embedded Security
              Install an SDK inside an application you own.
              → This project.

Market 2 — External Monitoring
              Proxy, browser extension, desktop monitoring, OS-level
              monitoring, network monitoring.
              → A different project entirely.
```

Trying to serve both at once with one design would fail at both. The decision, made without hesitation:

> **This layer builds an Embedded Security SDK — not a Universal Monitor.**

This fits the reality of the ideal customer: companies with real, owned AI Agents already have source code access — they are, by definition, exactly the Type 1 customer this layer is built for.

### The Honest Marketing Claim

Rather than the false, appealing claim *"ASES supports any Agent,"* the accurate claim adopted going forward is:

> **"ASES integrates with any Python-based AI Agent that exposes an integration point (callbacks, middleware, hooks, or custom events)."**

This is precise, defensible, and — critically — entirely achievable, unlike the alternative.

---

## 4. Proving the Design Against a Real Framework, Not a Toy Agent

A deliberate decision follows from this: the first proof of the architecture must run against a **real, existing framework** — CrewAI — rather than a custom-built demo Agent designed to make the architecture look good. If it works against CrewAI, and later against LangGraph too, that's real evidence the Core is genuinely framework-agnostic (see [`07-agent-framework-ecosystem.md`](./07-agent-framework-ecosystem.md)) — not evidence manufactured to order.

---

## 5. Who Is the Real Customer?

> **A company that owns, or is developing, its own AI Agents or AI workflows.**

Not "anyone using AI." Not "anyone with a ChatGPT Plus subscription." Not "anyone using Claude Code." This single sentence, stated plainly, prevents a large amount of downstream confusion.

---

## 6. The Full Journey, Stage by Stage

### Stage 1 — Discover
The customer lands on the ASES site and watches a short explainer that states the honest scope directly: *"If you have a Python AI Agent, you can add a security monitoring layer to it without changing its business logic."* Note the careful phrasing — **without changing business logic**, not **without any change at all**, because the second claim would be false.

### Stage 2 — Compatibility Check
The first real question the product asks:

```text
What did you build your Agent with?

○ CrewAI
○ LangGraph
○ OpenAI Agents SDK
○ Custom Python Agent
○ Other
```

This single answer determines the rest of the integration path — the journey is never forced to be identical for every customer.

### Stage 3 — Register Agent
The customer registers the **Agent's metadata**, not its code: Name, Framework, Version, Environment, Description, through the SentinelX Dashboard. ASES issues an **Agent API Key** — the Agent's official identity (see the Authentication documentation for the full credential design). Mechanically, this is two sequential Backend calls (create the Agent, then issue its first API Key) — the Dashboard chains them automatically and invisibly, so the customer experiences this stage as the one seamless step described above (RC-8, PUBCONTRACT-004; see `backend/docs/03-agent/adr/ADR-001-two-step-provisioning.md` for why the Backend itself requires two calls, and why the Dashboard, not a raw API-driven flow, is what this stage is describing).

### Stage 4 — Install
Installing is deliberately separated from integrating. Installing is one command:

```bash
pip install ases
```

Nothing has been changed about the Agent yet.

### Stage 5 — Integration
The most important stage, and the one this whole document exists to clarify. The right question was never *"will the customer modify anything?"* — the honest answer to that is almost always yes. The right question is:

> **"Modify *what*, exactly?"**

The answer: only the **integration point** — never the Agent's logic, prompts, tools, or workflow. If CrewAI offers an official callback, the customer wires the SDK to that callback and nothing else changes. They are not editing their Agent — they are **taking advantage of an extension point that already exists**.

### Stage 6 — First Run
The customer runs their Agent exactly as they always have. They do not separately "start ASES," open a special dashboard, or run any additional program. In the background, every Event begins turning into an Observation, gets collected, built, and sent — invisibly, from the customer's point of view.

### Stage 7 — Dashboard
After the first Task completes, the customer opens the Dashboard and sees:

**How long should this take?** Typically within a couple of minutes of submission under normal load — the Backend claims newly-submitted Observations in batches, once per minute (`analysis:poll-pending-observations`, `backend/routes/console.php`), so results are not instantaneous; if many Observations are queued at once, this can take longer, since each poll only claims a bounded batch. This is the one honest expectation ASES's own documentation can set on the customer's behalf — the SDK itself cannot, by design (see `adr/ADR-005-sdk-responsibility-boundary.md`: its own responsibility ends at `202 Accepted`, before any of this happens) — sourced from the Backend's own current, real scheduling behavior, not invented (RC-9, RELIABILITY-004/WALK-010).

```text
Observation #1
    ↓
Prediction
    ↓
Risk Score
    ↓
Evidence
    ↓
Status
```

If something risky was detected, an Alert appears.

---

## 7. Restating What "Integration" Actually Means

The original framing — *"the customer will modify their Agent"* — is replaced with the accurate one:

> **The customer connects the SDK to an integration point that already exists inside their Framework.**

This is a fundamentally different, and fundamentally more honest, description of the product.

---

## 8. What About Customers With No Framework? And No Code at All?

**No framework, but real Python code:** this is where the Generic Adapter and the Manual API (see [`03-agent-integration-models.md`](./03-agent-integration-models.md)) apply — a normal, supported scenario.

**No code at all** (Claude Code, Cursor, ChatGPT Agent): **ASES V1 does not support this scenario** — not because of a shortcoming, but because it is a different product category entirely. See [`07-agent-framework-ecosystem.md`](./07-agent-framework-ecosystem.md) for the "Desktop Sensor" future-vision note this realization produced.

---

## 9. The Full Journey, End to End

```text
1. Register with ASES
    ↓
2. Create an Agent inside the Dashboard
    ↓
3. Receive an API Key
    ↓
4. Install ases-sdk
    ↓
5. Wire the appropriate Adapter to your Agent
    ↓
6. Run the Agent
    ↓
7. ASES automatically begins receiving Observations
    ↓
8. View results on the Dashboard
```

---

## 10. The Deepest Realization From This Stage of Design

> **This layer does not sell an SDK. It sells a Security Integration Experience — and the SDK is merely the means.**

If a future path is ever found that lets a customer connect ASES without a package at all, that path should be taken, because the founding goal has always been: **customer ease first, technology second.**
