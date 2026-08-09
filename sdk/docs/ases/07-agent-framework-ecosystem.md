# 07 — Agent Framework Ecosystem

> Source concept: Session 4.7 — the last exploratory session before design resumed. This document grounds every remaining architectural decision in the real market, rather than assumptions.

---

## 1. Adapters Are Built for Execution Frameworks — Not Models, Not Agents

The first distinction this session established, and one worth stating precisely:

```text
OpenAI, Anthropic, Gemini     →  these are Models.
CrewAI, LangGraph, AutoGen,
Google ADK                     →  these are Frameworks.
```

Frameworks control an Agent's **execution journey** — which is exactly where an Integration Point lives (see [`06-integration-point-concept.md`](./06-integration-point-concept.md)). Models do not expose that journey; frameworks do. This is why this layer builds Adapters for execution frameworks specifically, never for a model provider directly.

---

## 2. The Framework Evaluation

| Framework | Local | Free | Priority | V1 |
|-----------|-------|------|----------|-----|
| **CrewAI** | ✅ | ✅ | ⭐⭐⭐⭐⭐ | ✅ |
| **LangGraph** | ✅ | ✅ | ⭐⭐⭐⭐⭐ | ✅ |
| **OpenAI Agents SDK** | ✅ (framework) | SDK only — model inference is billed | ⭐⭐⭐⭐ | ✅ |
| AutoGen | ✅ | ✅ | ⭐⭐⭐ | Later |
| Google ADK | ✅ | ✅ | ⭐⭐ | Later |
| Mastra | ✅ | ✅ | ⭐⭐ | Later |

### Why CrewAI First
Open source, Python-native, widely adopted, excellent documentation, a large community, and — critically — built in a genuinely **modular** way with a clear lifecycle, which makes observation straightforward. It can be installed locally, for free, and a working Agent can exist within five minutes — making it viable for the project's own demos, not just for customers.

### Why LangGraph Second
Built around a Graph model — the Agent moves between Nodes. Every transition is naturally an Event, which means ASES sees the execution journey in fine-grained detail. Free, local, and Python — the same qualifying profile as CrewAI.

### OpenAI Agents SDK — a Nuance Worth Recording
The framework itself is free and open, but running the underlying model incurs real usage cost. This has no bearing on ASES's ability to integrate — it's simply a fact worth documenting so it isn't mistaken for an integration limitation later.

---

## 3. The CrewAI Adapter's `event_type` Mapping (RC-7, Ground 2)

The Adapter — not the Core — owns mapping its framework's own callback vocabulary into one of the Backend's ten canonical Event Dictionary values (`api_call`, `file_access`, `command_execution`, `network_connection`, `database_operation`, `tool_execution`, `memory_operation`, `authentication`, `configuration_change`, `custom`), consistent with the existing rule that the Core never knows a framework directly (`ADR-001-adapter-based-framework-strategy.md`) — the Builder stays framework-agnostic and never needs its own per-framework mapping table.

| CrewAI callback | Canonical `event_type` | Rationale |
|---|---|---|
| `TaskStartedEvent` | `custom` | A Task lifecycle marker, not one of the nine specific categories — the Event Dictionary's own catch-all. |
| `ToolUsageStartedEvent` | `tool_execution` | A direct match — CrewAI calling a Tool is exactly what this category describes. |
| `ToolUsageFinishedEvent` | `tool_execution` | Same category as its start event; still a Tool operation. |
| `LLMCallStartedEvent` | `custom` | No dedicated LLM-call category exists in the Backend's Event Dictionary. |
| `LLMCallCompletedEvent` | `custom` | Same reasoning as its start event. |

The original crewai callback name is preserved in the Event's `payload` (under a `crewai_event` key) so it isn't lost — only relocated out of the validated `header.event_type` field. The Backend never validates `payload` content beyond confirming it is an object (`backend/docs/04-observation/adrs/ADR-002-structural-validation-only.md`), so this is a safe place for it to live. This mapping is enforced, not just documented: `ases/pipeline/events.py`'s `EventSignal` rejects any `event_type` outside the ten canonical values at construction time, regardless of which Adapter produced it.

### AutoGen, Google ADK, Mastra — Acknowledged, Not V1
AutoGen (Microsoft) supports genuine multi-Agent systems — likely relevant to Sentinel's longer-term future in multi-Agent security, but not required for the MVP. Google ADK is being pushed hard by Google but isn't yet a priority. Mastra is modern but has a smaller community. All three remain on the roadmap, not the MVP.

---

## 3. Adapter Build Order

```text
1. CrewAI Adapter
2. LangGraph Adapter
3. OpenAI Agents SDK Adapter
```

This order is sufficient for V1 — see [`13-implementation-roadmap.md`](./13-implementation-roadmap.md) for exactly where each lands in the build sequence.

---

## 4. Can All of This Be Tested Locally?

**Yes — and this fact materially de-risks the entire project.**

```text
CrewAI                   → fully local
LangGraph                 → fully local
AutoGen                    → fully local
Google ADK                   → fully local
OpenAI Agents SDK              → framework local, model inference typically online
```

It's possible to go further: using local model runtimes (Ollama, LM Studio, vLLM), an entire Agent — framework and model both — can run without any cloud dependency at all. This means Sentinel's whole pipeline can be demonstrated end to end with zero external services, which is a genuine strength for demos, evaluation, and offline development.

---

## 5. Does One Adapter Work for All Frameworks?

**No — but one Core does.**

```text
CrewAI      → CrewAI Adapter      → ASES Core
LangGraph    → LangGraph Adapter    → ASES Core
AutoGen       → AutoGen Adapter       → ASES Core
```

The only thing that changes per framework is the Adapter. The Core changes **zero lines**. This is the concrete, testable proof of the "Adapter-based, not Framework-specific" decision established in [`03-agent-integration-models.md`](./03-agent-integration-models.md) — see [`ADR-001-adapter-based-framework-strategy.md`](./adr/ADR-001-adapter-based-framework-strategy.md).

---

## 6. Applications vs. Frameworks — Why Claude Code, Cursor, and ChatGPT Desktop Are Out of Scope

A distinction worth making with complete precision:

```text
Can I run Claude Code on my machine?     Yes.
Can I add ASES inside it?                  No.
```

**Why:** Claude Code is an **Application**, not a **Framework**. As a user of it, there is no Runtime ownership — and without Runtime ownership, there is no Integration Point (see [`06-integration-point-concept.md`](./06-integration-point-concept.md)). The identical reasoning applies to ChatGPT Desktop and Cursor Chat — all of them are Applications, not Frameworks.

---

## 7. Future Vision: A Second, Distinct Product

This gap is worth recording deliberately, because it points toward something real — just not something to build now.

```text
ASES SDK              → for companies with their own Agents.
Sentinel Desktop Sensor  → for individual users of applications like
                              Claude Code, Cursor, VS Code, ...
```

This would be a **new product**, not a feature of this layer. It is explicitly filed under **Future Products**, not the current backlog — a distinction that matters, because folding it into this layer's backlog would blur two genuinely different product categories the way [`05-customer-integration-journey.md`](./05-customer-integration-journey.md) already warned against.

---

## 8. The Sentence This Session Produced

Rather than the inaccurate, if appealing, claim *"we monitor anything with AI in it,"* the accurate and still compelling claim adopted here is:

> **Sentinel protects AI Agents whose execution lifecycle you own, through the ASES layer that integrates with the official AI Framework you use.**

This is engineering-accurate, still strong from a positioning standpoint, and — the important part — 100% achievable.

---

## 9. Summary

```text
The AI Agent Ecosystem

Goal
Study the real, current AI Agent framework landscape and determine:
does it run locally? can an Adapter be built for it? does it belong
in V1?

────────────────────────

Architecture Decision
ASES Core remains unchanged.
Only Adapters change, per framework.

────────────────────────

Supported Customer
Companies that own or develop Python-based AI Agents.

────────────────────────

Not Supported in V1
Claude Code, Cursor AI, ChatGPT Desktop, and other closed-source AI
applications. These require a different product category (a Desktop
Sensor), not the ASES SDK.
```

With this session complete, every downstream design question — module boundaries, internal architecture, the Observation Lifecycle — has a real, evidenced answer to build against: who the customer is, what the market boundary is, what is and isn't supported, how it will be tested locally, and which framework comes first.
