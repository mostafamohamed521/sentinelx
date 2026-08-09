# ADR-001: Sprint 6's Scope Is a Single Aggregation Endpoint, Not Five Separate Features

| | |
|---|---|
| **Status** | ✅ Accepted |
| **Scope** | Dashboard Module, Sprint 6 |
| **Affects** | What actually gets built this Sprint, and what doesn't |

---

## Context

[`08-sprint-roadmap.md`](../../00-backend_architecture/00-backend_architecture/08-sprint-roadmap.md) §8 describes this Sprint's work as a five-step chain: `Overview → Observation History → Alert History → Search → Filters`. Read at face value, alongside [`04-module-responsibilities.md`](../../00-backend_architecture/00-backend_architecture/04-module-responsibilities.md) §8's description of Dashboard as aggregating four kinds of summary data, this could plausibly be interpreted as five separate deliverables. The actual, specific, frozen API contract — [`DASHBOARD_API.md`](../../docs/docs/09-api-reference/06-DASHBOARD_API.md) — defines exactly one endpoint.

---

## Decision

This Sprint builds exactly one new backend deliverable: `GET /api/v1/dashboard`. "Observation History" and "Alert History" require no new code (they're `GET /observations` and `GET /alerts`, already fully built and frozen in Stage 3 and Stage 5). "Filters" requires no new code (already-existing query parameters on those same two endpoints). "Search" is not built at all, because no frozen document anywhere specifies what it would search over or how.

---

## Rationale

### Why trust the specific API reference over the roadmap's prose outline, the same way ADR-002 in the Alert module trusted `ALERTS_API.md` over `module-responsibilities.md`?
Same reasoning, fourth time this exact judgment call has come up in this series in one form or another: a specific, endpoint-level contract is lower-risk to build against than a high-level prose outline written earlier in the design process. `08-sprint-roadmap.md`'s five-step list reads naturally as *"here's the rough shape of what this Sprint touches,"* not as *"here are five distinct API surfaces to build,"* especially once cross-referenced against what `DASHBOARD_API.md` — the artifact actually meant to be built against — specifies.

### Isn't it safer to build "Search" anyway, just in case it's genuinely wanted?
No — building an unspecified feature invites exactly the kind of guessed-at, likely-wrong implementation this documentation series exists to prevent. A search feature has real design questions (which fields? full-text or exact match? scoped to Observations, Alerts, or both? paginated how?) that no frozen document answers. Guessing at all of them risks shipping something that has to be reworked once real requirements surface, which costs more than building nothing and flagging the gap.

### Doesn't skipping "Observation History" and "Alert History" mean under-delivering on the Sprint's own stated goals?
No — those goals are already fully met. The Sprint roadmap's own five-word summary was written at a level of abstraction that predates the detailed, endpoint-by-endpoint design this documentation series subsequently produced for Stage 3 and Stage 5. Confirming that those two "History" items are already satisfied by existing work is itself a valid, complete resolution — not a shortfall.

---

## Rejected Alternatives

| Alternative | Reason for Rejection |
|-------------|------------------------|
| Build a new, Dashboard-owned Observation/Alert listing endpoint, distinct from the existing ones | Directly violates Dashboard's own "owns no data" rule (`01-overview.md` §4) and creates two competing sources of truth for the same list |
| Build a generic full-text search endpoint speculatively | No frozen document specifies its scope or semantics; high risk of building the wrong thing |
| Treat the roadmap document as authoritative and block this Sprint pending clarification of "Search" | Disproportionate — the ambiguity is resolvable with reasonable judgment (as this ADR does), and blocking an entire Sprint over one underspecified word is not warranted |

---

## Consequences

- ✅ Sprint 6 ships exactly the endpoint the frozen, specific API contract defines — no redundant, competing implementations of already-existing functionality.
- ✅ No unspecified "Search" feature is guessed at and potentially built wrong.
- ✅ The actual amount of new code this Sprint requires is small and well-bounded — one endpoint, plus four narrow, additive read contracts (see `04-aggregation-contracts.md`) — which is itself a useful, correct signal about this Sprint's real size.
- ⚠️ If "Search" is a genuine, still-wanted requirement, it remains fully unaddressed after this Sprint — flagged explicitly here so it isn't lost, and pointed toward the Observation module (the actual data owner) as its natural home if and when it's specified.
