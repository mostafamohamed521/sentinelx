# 08 — Observation Module Implementation Roadmap

> Converts everything designed in this folder into a build plan, following the exact same rule set as [`docs/backend/agent/07-implementation-roadmap.md`](../03-agent/07-implementation-roadmap.md) and the Layer order from [`06-implementation-layers.md`](../00-backend_architecture/00-backend_architecture/06-implementation-layers.md).

---

## 1. Where This Sits

```text
Sprint 0 — Foundation           ✅ Done
Sprint 1 — Identity Foundation  ✅ Done
Sprint 2 — Agent Foundation     ✅ Done
Sprint 3 — Observation Pipeline 🟢 This roadmap
```

**Definition of Done for Sprint 3** (per [`08-sprint-roadmap.md`](../00-backend_architecture/00-backend_architecture/08-sprint-roadmap.md) / [`07-implementation-order.md`](../00-backend_architecture/00-backend_architecture/07-implementation-order.md)):

> `SDK → POST /observations → Validation → Database, with zero mocks.`

By the end of this Sprint, an Agent (real API Key, issued in Sprint 2) can submit a real ASES-shaped Observation, have it validated and stored, and a Human can log in and see it in the list and detail views — a complete, demoable, testable increment, entirely independent of ML.

---

## 2. Build Order (Layer by Layer, Observation Module Only)

```text
1. Domain
   ├── Observation (domain object / invariants from 02-domain.md §7)
   └── ObservationValidator (the 5 structural checks from 04-validation.md §3)

2. Infrastructure (Persistence)
   ├── ObservationModel (Eloquent)
   ├── ObservationRepository
   │     ├── create()
   │     ├── findById(id, organizationId)
   │     ├── listForOrganization(organizationId, filters, pagination)
   │     ├── listForAgent(agentId, organizationId, pagination)
   │     ├── markProcessing(id)     ← exposed now, consumed by Analysis in Stage 4
   │     ├── markCompleted(id, processedAt)  ← exposed now, consumed later
   │     └── markFailed(id, processedAt)      ← exposed now, consumed later
   └── ObservationLookupContract (interface) + implementation
         — the only surface Analysis (Stage 4) may later call to read an
           Observation without owning it (see 05-cross-module-boundaries.md §3)

3. Application (Actions)
   ├── ReceiveObservationAction
   │     — validates, persists, calls AgentRepository::touchLastSeen() in the
   │       same transaction (see 05-cross-module-boundaries.md §1)
   ├── ListObservationsAction
   ├── ListAgentObservationsAction
   │     — validates the Agent exists/belongs to the Organization via
   │       Agent\Application\Contracts\AgentLookupContract before querying
   └── GetObservationAction
         — returns Observation fields with prediction hardcoded to null
           (Stage 3 scope — see 07-api-contract.md §3)

4. Presentation
   ├── ObservationResource          (full detail — includes raw_ases_json)
   ├── ObservationSummaryResource   (list view — excludes raw_ases_json)
   └── ObservationAcceptedResource  (POST response — minimal, per 03-ingestion-pipeline.md §7)

5. API
   ├── ObservationController
   │     ├── store()   → ReceiveObservationAction   (API Key guard only)
   │     ├── index()    → ListObservationsAction      (JWT guard, any Role)
   │     └── show()      → GetObservationAction         (JWT guard, any Role)
   ├── AgentObservationController
   │     └── index()    → ListAgentObservationsAction  (JWT guard, any Role)
   ├── SubmitObservationRequest (FormRequest — Checks 1/2/3/5 from 04-validation.md
   │     expressible as standard validation rules; Check 4, chronological ordering,
   │     enforced in ObservationValidator, not here)
   └── Routes:
         POST   /observations                    → auth:api-key
         GET    /observations                     → auth:jwt
         GET    /observations/{id}                 → auth:jwt
         GET    /agents/{agentId}/observations       → auth:jwt
```

**Note the order:** Domain and Infrastructure before Application, Application before Presentation/API — identical bottom-up order to Sprint 2.

---

## 3. What Sprint 3 Explicitly Does NOT Build

```text
✘ Any Queue/Worker infrastructure that consumes PENDING observations
✘ Any FastAPI/ML client
✘ Any code path that ever transitions analysis_status away from PENDING
✘ Any Prediction-related model, table interaction, or Resource field beyond a
  hardcoded `null`
```

These are real, indexed, planned-for capabilities (see [`01-database/schema/indexes.md`](../01-database/01-database/schema/indexes.md), Index 5 — "the single most important query in the entire backend") — but they are Stage 4's work, not Stage 3's. Building any of them now would front-run a module that doesn't exist yet and violate the dependency direction documented in [`05-cross-module-boundaries.md`](./05-cross-module-boundaries.md).

---

## 4. Tests Required (Following the Engineering Workflow's Five Categories)

```text
1. Happy Path
   ✔ An authenticated Agent can submit a well-formed Observation → 202,
     analysis_status = PENDING
   ✔ Owner/Admin/Member can list Observations for their Organization
   ✔ Owner/Admin/Member can view a single Observation, prediction: null
   ✔ Submitting an Observation updates the Agent's last_seen_at

2. Edge Case
   ✔ Observation with zero events → 422
   ✔ Observation with out-of-order event timestamps → 422
   ✔ Observation missing a required Context field → 422
   ✔ Malformed (non-JSON) request body → 400
   ✔ Observation with an events[].header.event_type outside the canonical
     Event Dictionary → 422

3. Business Rule
   ✔ organization_id and agent_id are always derived from the AuthenticatedIdentity,
     never from the request body, even if the client attempts to include them
   ✔ raw_ases_json is stored byte-for-byte identical to what was submitted
     (no re-serialization drift — assert deep equality, not just "no error")
   ✔ analysis_status is always exactly PENDING immediately after a successful POST
   ✔ No PATCH/PUT/DELETE route exists anywhere for observations (route-table
     assertion, not just a missing-test gap)

4. Authorization
   ✔ A request with a valid JWT (no API Key) attempting POST /observations → 401
     (wrong guard entirely — the route doesn't accept JWT)
   ✔ A request with a valid API Key attempting GET /observations → 401
     (wrong guard entirely — the route doesn't accept API Key)
   ✔ An Agent whose key was just Revoked cannot submit → 401
   ✔ An Agent belonging to an Archived state cannot submit → 401

5. Data Isolation
   ✔ Organization A cannot GET a single Observation belonging to Organization B → 404
   ✔ Organization A's list never includes Organization B's Observations
   ✔ An Agent's API Key can only ever produce Observations with that Agent's own
     agent_id — attempt to prove otherwise is structurally impossible to test via
     the API (no field accepts a different agent_id), so this is verified at the
     Application-layer unit-test level instead
```

---

## 5. Sprint 3 Exit Checklist

```text
☐ observations table already exists (Stage 1/Database — no new migration needed)
☐ ObservationValidator implements all 5 structural checks from 04-validation.md
☐ ObservationLookupContract implemented, ready for Analysis to consume in Stage 4
☐ ObservationRepository's markProcessing/markCompleted/markFailed methods exist
  and are unit-tested even though nothing calls them yet
☐ AgentRepository::touchLastSeen() confirmed called, in-transaction, on every
  successful Observation submission
☐ All 4 Observation-module endpoints implemented and passing tests
☐ Postman collection updated with the Observation folder, including a sample
  ASES payload matching docs.zip/03-specifications/03-ASES_JSON_SCHEMA.md
☐ docs/backend/observation/ (this folder) marked Frozen once code matches it exactly
```
