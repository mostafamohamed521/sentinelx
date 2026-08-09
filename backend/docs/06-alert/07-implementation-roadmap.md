# 07 — Alert Module Implementation Roadmap

> Converts everything designed in this folder into a build plan, following the exact same rule set as the previous three modules and the Layer order from [`06-implementation-layers.md`](../00-backend_architecture/00-backend_architecture/06-implementation-layers.md).

---

## 1. Where This Sits

```text
Sprint 0 — Foundation           ✅ Done
Sprint 1 — Identity Foundation  ✅ Done
Sprint 2 — Agent Foundation     ✅ Done
Sprint 3 — Observation Pipeline ✅ Done
Sprint 4 — ML Integration       ✅ Done
Sprint 5 — Alert Engine         🟢 This roadmap
```

**Definition of Done for Sprint 5** (per [`08-sprint-roadmap.md`](../00-backend_architecture/00-backend_architecture/08-sprint-roadmap.md) §7): **"the first real Alert appears."**

---

## 2. Build Order (Layer by Layer, Alert Module Only)

```text
1. Domain
   ├── Alert (domain object / invariants from 02-domain.md §8)
   └── SeverityMapper — fromRiskScore(int): Severity, implementing ADR-001's thresholds
        as one isolated, easily-adjusted function

2. Infrastructure
   ├── AlertModel (Eloquent)
   ├── AlertRepository
   │     ├── create()
   │     ├── findById(id, organizationId)   — joins through Prediction → Observation
   │     │     to scope by organization, per 04-authorization.md §5
   │     ├── listForOrganization(organizationId, filters, pagination)
   │     ├── acknowledge(id, userId, acknowledgedAt)
   │     └── resolve(id, userId, resolvedAt)
   └── AlertSummaryContract (interface) + implementation
         — exposed now for Dashboard (Stage 6) to consume later

3. Application
   ├── AcknowledgeAlertAction
   ├── ResolveAlertAction
   ├── ListAlertsAction
   └── GetAlertAction
         — composes Prediction (via Analysis's PredictionLookupContract) and
           Observation (via Observation's own already-exposed ObservationLookupContract)
           per 06-api-contract.md §2

4. Infrastructure (Listener)
   └── EvaluateAlertPolicyOnPredictionStored
         — subscribes to Analysis's PredictionStored event; implements the policy
           from 03-generation-pipeline.md §3

5. Presentation
   ├── AlertSummaryResource   (list view)
   └── AlertDetailResource    (single view, with embedded prediction + observation)

6. API
   ├── AlertController
   │     ├── index()      → ListAlertsAction
   │     ├── show()         → GetAlertAction
   │     ├── acknowledge()    → AcknowledgeAlertAction
   │     └── resolve()          → ResolveAlertAction
   └── Routes (auth:jwt only — no API Key guard anywhere in this module,
        Owner/Admin/Member all permitted per 04-authorization.md §4)

7. Tests, for every layer above — see the 5 categories below
```

---

## 3. The One Small, Additive Change Required Elsewhere (Analysis Module)

As detailed in [`05-cross-module-boundaries.md`](./05-cross-module-boundaries.md) §2: Analysis's existing `AnalyzeObservationAction` gets exactly one new line — `dispatch(new PredictionStored(...))` — after its existing `markCompleted()` call. No other file inside the Analysis module changes. This is called out explicitly here, the same way Sprint 4's roadmap called out its own small addition to Observation, so it isn't missed or mistaken for scope creep.

Additionally, per [`06-api-contract.md`](./06-api-contract.md) §2, `PredictionLookupContract` gains one more read method (`findById`, alongside the existing `findByObservationId`) — another small, additive change to an already-existing Analysis-owned interface, not a new contract.

---

## 4. What Sprint 5 Explicitly Does NOT Build

```text
✘ Any Dashboard aggregation, widget, or rendering — Stage 6's job; this Sprint only
  exposes AlertSummaryContract for Stage 6 to later consume
✘ A reopen endpoint or ACKNOWLEDGED/RESOLVED → OPEN transition — see ADR-002; flagged
  as a documentation discrepancy, not silently built or silently dropped
✘ Any external notification (email, Slack, webhook) — not specified in any frozen
  document for V1
✘ Organization-configurable severity thresholds — ADR-011 anticipates this for a
  future version; V1 uses the fixed mapping from ADR-001
```

---

## 5. Tests Required (Following the Engineering Workflow's Five Categories)

```text
1. Happy Path
   ✔ A MALICIOUS Prediction results in an Alert being created with the correct severity
   ✔ A SUSPICIOUS Prediction results in an Alert being created with the correct severity
   ✔ Owner/Admin/Member can list Alerts for their Organization
   ✔ Owner/Admin/Member can view a single Alert with embedded Prediction + Observation
   ✔ Owner/Admin/Member can acknowledge an OPEN Alert
   ✔ Owner/Admin/Member can resolve an OPEN or ACKNOWLEDGED Alert

2. Edge Case
   ✔ A SAFE Prediction never produces an Alert (assert no row created, not just "no error")
   ✔ risk_score at each threshold boundary (24/25, 49/50, 74/75) maps to the correct
     Severity — off-by-one errors here are the single most likely bug in this Sprint
   ✔ Acknowledging an already-ACKNOWLEDGED Alert → 409
   ✔ Resolving an already-RESOLVED Alert → 409
   ✔ Resolving directly from OPEN (skip-ahead) succeeds
   ✔ Two PredictionStored events for the same prediction_id (a defensive duplicate-event
     scenario) never produce two Alert rows — the UNIQUE constraint plus the idempotency
     check both hold

3. Business Rule
   ✔ severity is never accepted from any request body — always server-computed via
     SeverityMapper
   ✔ acknowledged_by / resolved_by always match the authenticated User's ID, never
     client-suppliable
   ✔ No route exists for POST /alerts or DELETE /alerts/{id} (route-table assertion)

4. Authorization
   ✔ All three Roles (Owner/Admin/Member) can perform every action in this module —
     explicitly assert this symmetry, since it's the one place in the backend so far
     where every Role behaves identically
   ✔ An Agent (API Key) attempting any endpoint in this module → 401 (wrong guard)
   ✔ Unauthenticated request → 401

5. Data Isolation
   ✔ Organization A cannot GET/acknowledge/resolve an Alert belonging to Organization B
     → 404
   ✔ Organization A's list never includes Organization B's Alerts
```

---

## 6. Sprint 5 Exit Checklist

```text
☐ alerts table already exists (Stage 1/Database — no new migration needed)
☐ SeverityMapper implements the exact thresholds from ADR-001, unit-tested at every
  boundary value
☐ PredictionStored event added to Analysis, dispatched from AnalyzeObservationAction
  (confirm no other Analysis file changed)
☐ PredictionLookupContract extended with findById() alongside the existing
  findByObservationId()
☐ EvaluateAlertPolicyOnPredictionStored listener registered and tested, including the
  SAFE-verdict no-op path and the idempotency guard
☐ AlertSummaryContract implemented, ready for Dashboard to consume in Stage 6
☐ All 4 Alert-module endpoints implemented and passing tests
☐ A real, end-to-end demo works: submit an Observation whose analysis yields
  SUSPICIOUS/MALICIOUS, and watch a real Alert appear via GET /alerts
☐ docs/backend/alert/ (this folder) marked Frozen once code matches it exactly
```
