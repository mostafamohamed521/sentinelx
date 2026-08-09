# ADR-005: SDK Responsibility Ends at HTTP 202 Accepted — The SDK Never Learns the ML Result

| | |
|---|---|
| **Status** | ✅ Accepted (Frozen) |
| **Source** | Session 7 — Transport Layer |
| **Affects** | The API Client's contract, and the architectural boundary between the client-side Integration Layer and the SentinelX Backend |

---

## Context

Once an Observation is successfully delivered to the SentinelX REST API, further processing happens entirely server-side: the Observation is analyzed by ML, a Prediction is produced, and an Alert may be generated. A decision was needed about whether the SDK should track, wait for, or otherwise remain aware of any of that downstream processing.

---

## Decision

**The SDK's responsibility for a given Observation ends the moment SentinelX responds with `202 Accepted`.** The SDK never learns whether ML returned a Prediction, never polls for a result, and holds no state about anything that happens after handoff.

---

## Rationale

### Client and Backend Must Stay Cleanly Decoupled
If the SDK tracked downstream results, it would need to know about Predictions, Risk Scores, and Alerts — concepts that belong entirely to the Backend's domain (see the Backend Architecture and Database documentation). Importing that awareness into the client would blur a boundary that exists specifically to keep the two independently evolvable.

### It Would Reintroduce Exactly the Coupling This Layer Was Built to Avoid
Every other decision in this Integration Layer — the Thin Adapter principle, the Observation/Analysis separation echoed from the Backend Architecture, the Passive Transport design — exists to keep components from absorbing responsibilities that belong elsewhere. Tracking server-side outcomes from the client would be the same mistake, at the client/server boundary instead of an internal one.

### `202 Accepted` Is the Correct, Honest Signal
An HTTP `202 Accepted` response means precisely what the SDK needs it to mean: *"received, and will be processed"* — not *"processed, here is the result."* Treating this as the end of SDK responsibility is both architecturally clean and technically accurate to what the response code actually promises.

---

## Rejected Alternatives

| Alternative | Reason for Rejection |
|-------------|------------------------|
| The SDK polls SentinelX for the Observation's Prediction/Alert result | Introduces unnecessary network traffic and client-side state for information the customer only needs inside the Dashboard, not inside their Agent process |
| The SDK holds open a connection or webhook listener for asynchronous results | Adds real operational complexity (open connections, listener lifecycle) to a component whose central design goal is to be lightweight and passive |
| The Backend returns the full Prediction synchronously in the original response | Would force Observation ingestion and ML analysis to happen synchronously in the same request, contradicting the Backend's own Observation/Analysis separation (see the Backend Architecture documentation) |

---

## Consequences

- ✅ The client and server evolve independently — changes to ML models, Prediction shape, or Alert logic never require any SDK change.
- ✅ The SDK's memory and complexity footprint stays minimal — no result-tracking state of any kind.
- ✅ Customers get results the same way regardless of integration method: by viewing the Dashboard, never by inspecting SDK state.
- ⚠️ Customers who want to react programmatically to a Prediction or Alert (e.g., an automated response) must use the SentinelX API or Dashboard directly — this is explicitly out of scope for the Integration Layer, and should be documented as such rather than treated as a future SDK feature by default.
