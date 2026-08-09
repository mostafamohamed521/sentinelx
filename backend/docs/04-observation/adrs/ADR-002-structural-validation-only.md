# ADR-002: The Observation Module Performs Structural Validation Only, Never Semantic Judgment

| | |
|---|---|
| **Status** | ✅ Accepted |
| **Scope** | Observation Module |
| **Affects** | `ObservationValidator`, the entire Domain layer of this module |

---

## Context

An ASES payload contains rich, security-relevant content — API calls, file access, command execution, network connections. It is tempting, while writing a "validation" layer that already parses and inspects this payload closely, to add a check like *"reject Observations containing an obviously dangerous `command_execution` payload"* — especially early in a project, before the ML Engine exists, as a stopgap. [`ASES_SPECIFICATION.md`](../../docs/docs/03-specifications/01-ASES_SPECIFICATION.md) already frozen the ownership boundary: *"ASES defines only the structure of an Observation. It does not define: Security severity, Threat classification, Attack taxonomy, Risk scoring, ML predictions."*

---

## Decision

The Observation module's validator checks exactly the five structural rules enumerated in [`04-validation.md`](../04-validation.md) §3 — JSON well-formedness, schema conformance, required fields, chronological event ordering, non-empty event list — and nothing else, ever. No stopgap heuristic security check is added, even temporarily, even before Analysis exists.

---

## Rationale

### Why not add a "basic" security heuristic now, since ML doesn't exist yet in Stage 3?
Because any such heuristic — however basic — is, by definition, a threat-classification decision, which [`04-module-responsibilities.md`](../../00-backend_architecture/00-backend_architecture/04-module-responsibilities.md) §5 already assigns exclusively to Analysis, and [`ADR-006-Backend-as-Orchestrator`](../../docs/docs/07-adrs/ADR-006-Backend-as-Orchestrator.md) already assigns exclusively to the ML Engine. A "temporary" heuristic has a well-documented failure mode in real systems: it quietly becomes permanent, gets extended piecemeal, and by the time Analysis actually ships, two disagreeing sources of truth about "is this dangerous" exist in the codebase — one in Observation's stopgap check, one in the real ML Engine — and nobody remembers to remove the first one.

### Why is `events[].payload`'s internal shape validated only as "is an object," never deeper?
Because payload shape is inherently event-type-specific and framework-specific, and evolves independently of the Backend's own release cycle (a new SDK version can start sending a richer `tool_execution` payload without any Backend change required, as long as it's still valid JSON). Validating payload internals here would silently couple this module's release cycle to every SDK's payload evolution — exactly the coupling [`ADR-001-Canonical-Observation`](../../docs/docs/07-adrs/ADR-001-Canonical-Observation.md) was written to prevent at the format level.

---

## Rejected Alternatives

| Alternative | Reason for Rejection |
|-------------|------------------------|
| Reject Observations containing obviously suspicious keywords (e.g., `rm -rf`, `DROP TABLE`) in `command_execution`/`database_operation` payloads | Directly performs threat classification — explicitly out of scope per `ASES_SPECIFICATION.md` and `04-module-responsibilities.md`; also trivially bypassable and gives false confidence |
| Validate `events[].payload` against a per-event-type JSON Schema | Couples this module's deploy cycle to every event type's payload evolution across every supported framework — a maintenance burden with no product requirement driving it in V1 |
| Reject Observations from an Agent whose `framework` doesn't match a known allowlist | No such allowlist exists or is planned — `framework` is documented as free text specifically to avoid this coupling (see `docs/backend/agent/02-domain.md` §2) |

---

## Consequences

- ✅ The Observation module's validation logic is small, stable, and framework-agnostic — it will not need to change as new AI frameworks or event payload shapes emerge.
- ✅ There is exactly one place in the entire platform where "is this dangerous" is decided — the ML Engine, via Analysis — with no risk of a second, forgotten, disagreeing implementation living in Observation.
- ⚠️ Until Analysis ships (Stage 4), a genuinely malicious Observation is accepted, stored, and simply sits `PENDING` forever with no judgment ever rendered on it — this is a known, accepted, temporary characteristic of Stage 3 being a real, working, but intentionally incomplete increment, not a security gap to patch over with an out-of-scope heuristic.
