# 01 — Alert Module Overview

> Extends [`backend-architecture/03-system-modules.md`](../00-backend_architecture/00-backend_architecture/03-system-modules.md) and [`04-module-responsibilities.md`](../00-backend_architecture/00-backend_architecture/04-module-responsibilities.md) §7. Nothing here contradicts them.

---

## 1. What Is an Alert, In One Sentence?

> **An Alert is the platform's operational response to a Prediction the ML Engine judged non-`SAFE` — a durable, trackable "someone needs to look at this" record, separate from the security judgment it's based on.**

Per [`01-database/schema/relationships.md`](../01-database/01-database/schema/relationships.md) §3: *"a `SAFE` verdict will never produce an Alert"* — the `Prediction → Alert` relationship is `1 → 0..1`, optional, exactly because most Predictions never need one.

---

## 2. What the Alert Module Is Responsible For

```text
Notice a completed Prediction with a non-SAFE verdict
Translate its risk_score into an operational Severity (LOW/MEDIUM/HIGH/CRITICAL)
Create the Alert record
Let a Human acknowledge it ("I've seen this")
Let a Human resolve it ("this incident is handled")
List Alerts, scoped to Organization
```

## 3. What the Alert Module Is Explicitly NOT Responsible For

```text
✘ Deciding whether an event is malicious          → ML Engine, via Analysis (already decided
                                                        by the time this module ever sees a Prediction)
✘ Computing a risk score or confidence               → Analysis owns this entirely
✘ Re-analyzing or second-guessing a verdict            → never — SAFE stays SAFE, this module
                                                            has no override capability
✘ Notifying anyone outside the platform (email,          → not in any frozen document for V1;
   Slack, webhooks)                                         explicitly out of scope here
```

Per [`04-module-responsibilities.md`](../00-backend_architecture/00-backend_architecture/04-module-responsibilities.md) §7: *"Does NOT: Perform any analysis. Communicate with ML."* This is, by the baseline's own description, *"the simplest module"* in the entire backend.

---

## 4. The One Rule This Module Never Breaks

> **The Alert module never touches, questions, or overrides a Prediction's verdict, confidence, or risk_score. Its only judgment call is the one policy decision it's explicitly been handed: "does this verdict, at this risk level, warrant an Alert, and at what severity?"**

Per [`04-module-responsibilities.md`](../00-backend_architecture/00-backend_architecture/04-module-responsibilities.md) §14: *"`Alert` never computes a Risk Score — `Analysis` does. This prevents duplicated logic across the system."* If a future engineer's instinct is *"let me also let a Human manually override a verdict from this screen"* — that's a feature request for a different system than the one described in [`ADR-003-ML-Responsibility`](../docs/docs/07-adrs/ADR-003-ML-Responsibility.md), which assigns threat classification to the ML Engine *exclusively*.

---

## 5. Why Alert Is Its Own Module and Not Folded Into Analysis

Per [`04-module-responsibilities.md`](../00-backend_architecture/00-backend_architecture/04-module-responsibilities.md) §12: *"The intuitive-but-wrong answer is Dashboard or Observation... an Alert is a result of analysis, not a result of receiving an Observation."* Folding Alert into Analysis, meanwhile, would mix a policy/operational concern (should a Human be notified? has it been handled?) with a pure ML-orchestration concern (call ML, store the result) — two things that evolve on entirely different schedules. Per [`05-module-dependencies.md`](../00-backend_architecture/00-backend_architecture/05-module-dependencies.md) §17's own loose-coupling test: *"Remove Alert entirely → does Analysis still work? Yes."*

---

## 6. Route Ownership vs. Module Ownership

Unlike Analysis (Stage 4, no routes of its own), the Alert module owns real, standalone public routes — this is the first module since Observation (Stage 3) to do so:

| Route | Implemented By |
|-------|------------------|
| `GET /alerts` | **Alert module** |
| `GET /alerts/{id}` | **Alert module** — composes in the related Observation and Prediction (see [`06-api-contract.md`](./06-api-contract.md) §2, same read-only composition pattern already used in Stage 4) |
| `PATCH /alerts/{id}/acknowledge` | **Alert module** |
| `PATCH /alerts/{id}/resolve` | **Alert module** |

No route on this list requires reaching into a module Alert isn't allowed to depend on — `GET /alerts/{id}`'s composition pulls from Observation and Analysis, both of which sit *below* Alert in the dependency chain (`Alert → Analysis → Observation → Agent → Organization`), so this is a normal, permitted read, not a repeat of the deferred-composition pattern seen in Stage 3/4.

---

## 7. Session Summary

```text
Alert Module — Overview

Owns
✔ Alert entity
✔ Alert Status (state machine)
✔ Resolution

Does Not Own
✘ Prediction
✘ The security judgment itself

Golden Rule
✔ Alert never overrides a verdict, risk_score, or confidence — it only decides
  whether and how loudly to surface what Analysis already concluded.
✔ This is, by design, the simplest module in the backend.
```
