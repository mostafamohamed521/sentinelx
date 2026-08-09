# ADR-003: `GET /observations/{id}` Prediction Composition Is Deferred to Analysis (Stage 4)

| | |
|---|---|
| **Status** | ✅ Accepted |
| **Scope** | Observation Module, Analysis Module (forward-looking) |
| **Affects** | `GET /observations/{id}` response contract across Stage 3 and Stage 4 |

---

## Context

[`OBSERVATIONS_API.md`](../../docs/docs/09-api-reference/04-OBSERVATIONS_API.md) specifies: *"Returns a single Observation together with its associated Prediction, if available."* But `predictions` is owned by the Analysis module, which does not exist yet in Stage 3, and per [`05-module-dependencies.md`](../../00-backend_architecture/00-backend_architecture/05-module-dependencies.md) §5, the dependency direction is `Analysis → Observation`, never the reverse — meaning the Observation module is not permitted to reach into Analysis's data even once Analysis exists.

This is structurally the same problem already solved in Stage 2 for `POST /agents` + API Key issuance (see [`docs/backend/agent/adr/ADR-001-two-step-provisioning.md`](../../03-agent/adr/ADR-001-two-step-provisioning.md)) — a single user-facing endpoint whose full response requires data from two modules with a one-way dependency between them.

---

## Decision

In Stage 3, `GET /observations/{id}` is implemented entirely inside the Observation module and returns `"prediction": null` for every Observation, always — because no Prediction can exist yet. In Stage 4, this same endpoint's *implementation* moves to be owned by the Analysis module, which composes the full response by calling the Observation module's own `ObservationLookupContract` (read-only, exposed now) to fetch the base Observation, then attaching its own Prediction data. **The response shape (`data.prediction` as a field that is sometimes `null`) never changes between Stage 3 and Stage 4** — only which module answers the request, and whether the field is populated, changes.

---

## Rationale

### Why not have the Observation module simply query `predictions` directly once that table exists?
Because doing so would make Observation depend on Analysis — the exact reverse of the frozen, one-way dependency graph. This is the identical reasoning already applied to Agent/API-Key in Stage 2: whichever module ends up implementing the composed endpoint must be the one *allowed* to depend on the other, not the more "obvious" or currently-convenient one.

### Why include `"prediction": null` in the Stage 3 response contract now, rather than adding the field only once Stage 4 ships?
Because adding a new field to an existing, already-integrated API response later is a safe, additive, non-breaking change — but *removing or renaming* a field a client has started depending on is not. Committing to the final shape (including the eventually-populated field, hardcoded to `null` for now) from the very first version means Stage 4 never requires any Dashboard/client-side contract change — only a value populating, which every reasonable client already handles gracefully.

### Why does route ownership itself move from Observation to Analysis between stages, rather than staying with Observation and just calling out to Analysis?
Because "staying with Observation and calling out to Analysis" is precisely the forbidden direction. The alternative — Observation exposing a *generic* `ObservationLookupContract` that any permitted caller (Analysis) can consume — is the only shape that respects the dependency graph while still allowing the composed response to exist. This exactly mirrors how `POST /agents/{id}/rotate-api-key` is documented under the Agent module's URL prefix but is implemented by Authentication (see `docs/backend/agent/01-overview.md` §5) — URL grouping and module/route implementation ownership are allowed to diverge, and already do, elsewhere in this same API.

---

## Rejected Alternatives

| Alternative | Reason for Rejection |
|-------------|------------------------|
| Observation module queries `predictions` directly once Stage 4 ships | Violates the frozen `Analysis → Observation` (not reverse) dependency direction |
| Keep `GET /observations/{id}` entirely inside Observation forever, with Analysis writing prediction data onto a denormalized column on `observations` | Contradicts the already-frozen decision that Predictions live in their own table, owned by Analysis, specifically so ML model changes don't touch the Observation module (see `01-database/schema/entities.md` §6) |
| Omit the `prediction` field entirely from the Stage 3 response, add it only in Stage 4 | Non-additive contract change — any Stage-3-built client that doesn't defensively handle unknown/missing fields could break when the field starts appearing; committing to the shape upfront avoids this entirely |
| Build this as a Dashboard-owned composed endpoint instead of Analysis-owned | Dashboard is explicitly "always the last consumer" and never something other modules or the public single-resource API depend on being available — `GET /observations/{id}` is a core resource endpoint, not a Dashboard aggregation, and shouldn't require Dashboard's existence to fully function |

---

## Consequences

- ✅ The dependency graph (`Observation ✘→ Analysis`) is never violated, even once Stage 4 ships.
- ✅ The public response contract is stable from Stage 3 onward — no breaking change for any client.
- ✅ `ObservationLookupContract`, built now with no consumer, becomes immediately useful the moment Analysis starts development, with zero rework required on the Observation side.
- ⚠️ Whoever builds Stage 4 must remember that implementing `GET /observations/{id}`'s full behavior is *their* task, not a leftover TODO inside the Observation module — this is explicitly called out in [`08-implementation-roadmap.md`](../08-implementation-roadmap.md) §3 to prevent it from being missed.
