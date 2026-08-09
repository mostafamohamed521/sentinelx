# 03 — Audit Logging: The Event Fan-In Pattern

> The sixth occurrence of the event-based cross-module pattern in this series — [`docs/backend/agent/04-api-key-coordination.md`](../03-agent/04-api-key-coordination.md) (`AgentArchived`), [`docs/backend/alert/03-generation-pipeline.md`](../06-alert/03-generation-pipeline.md) (`PredictionStored`), and now the pattern generalized: **every** module fires events, **one** module (Audit) listens to all of them. This is the pattern's natural conclusion.

---

## 1. Why Fan-In, Not Direct Calls

Per [`05-module-dependencies.md`](../00-backend_architecture/00-backend_architecture/05-module-dependencies.md) §16: *"Audit only records. If Audit fails, `Login`, `Observation`, and `Alert` must all continue to function regardless."* If any module called Audit directly and synchronously (`AuditLogger::record(...)` inside `CreateAgentAction`, say), a failure inside Audit's own write path (a database hiccup, a full disk) could, in the worst implementation, block or fail the *primary* action being audited — exactly backwards from what a recorder is supposed to do. Event dispatch, listened to asynchronously or at least independently, guarantees the primary action's success is never contingent on Audit's own health.

---

## 2. The Mechanism

```text
Any module's Action (Agent, Organization, Authentication, Alert, ...)
    │
    └── dispatches a plain domain event, e.g.:
          AgentCreated(agentId, organizationId, actorUserId)
          AgentArchived(...)              ← ALREADY existed since Stage 2, for a
                                              different purpose (API Key revocation);
                                              Audit adds itself as a SECOND listener
                                              to this same event, changing nothing
                                              about Stage 2's own code
          OrganizationUpdated(...)
          UserProfileUpdated(...)
          UserPasswordChanged(...)
          AlertAcknowledged(...)
          AlertResolved(...)
    │
    ▼
Audit\Listeners\RecordAuditEvent (one listener class, or one per event type —
    implementation detail; see 09-implementation-roadmap.md)
    │
    └── INSERT INTO audit_logs (organization_id, actor_type, actor_id, action,
          resource_type, resource_id, metadata, created_at)
```

**No module dispatching one of these events has any reference to the Audit module.** Exactly the same discipline as every prior event in this series — the emitting module has zero knowledge that Audit, specifically, is listening, or that anything is listening at all.

---

## 3. What Gets Audited — the Scope Decision

Per [`adr/ADR-002-audit-scoped-to-human-initiated-actions.md`](./adr/ADR-002-audit-scoped-to-human-initiated-actions.md), only **Human-initiated, mutating, administratively-significant** actions are audited:

```text
Audited (V1):
  Organization       → updated
  User (Authentication) → registered, logged in, logged out, profile updated,
                              password changed, role changed (future, if Team
                              Management ships)
  Agent               → created, updated, archived
  API Key               → generated, rotated, revoked
  Alert                   → acknowledged, resolved

NOT audited (V1):
  Observation submission   → already fully, immutably recorded in `observations`
                                itself (Stage 3); auditing it a second time is pure
                                duplication at very high volume
  Prediction storage         → system-generated, no Human actor, already fully
                                  recorded in `predictions` (Stage 4)
  Alert creation (system-side)  → system-generated (the Alert Listener from Stage 5),
                                     no Human actor — the Alert's own created_at
                                     already records this
```

---

## 4. Action Naming Convention

```text
"<resource>.<verb>", lowercase, snake_case verb where multi-word

organization.updated
user.registered
user.logged_in
user.logged_out
user.profile_updated
user.password_changed
agent.created
agent.updated
agent.archived
api_key.generated
api_key.rotated
api_key.revoked
alert.acknowledged
alert.resolved
```

This convention is not frozen anywhere — it's this Sprint's own contribution, chosen for consistency and grep-ability, and listed exhaustively here so every listener implementation uses the exact same strings rather than each module inventing its own casing/format.

---

## 5. What Listens Where — Module Placement

```text
Each listener lives inside the AUDIT module (not scattered across the modules being
audited) — e.g., Audit\Listeners\RecordAgentCreated, Audit\Listeners\
RecordOrganizationUpdated, etc. — subscribing to events dispatched by other modules.

This mirrors the exact placement already used for
Alert\Listeners\EvaluateAlertPolicyOnPredictionStored (Stage 5) — the LISTENER
lives in the module that CARES about the event, never in the module that CAUSES it.
```

---

## 6. Failure Handling

```text
If a listener fails to write an audit_logs row:
    → logged (via the platform's normal application logging, per
      docs.zip/10-operational-architecture/03-LOGGING_STRATEGY.md's general
      structured-logging principle)
    → the triggering action (e.g., Agent creation) has ALREADY succeeded and
      committed by the time the event fires — Audit's failure never rolls back
      or blocks the primary action, per the Golden Rule in 01-overview.md §2
```

---

## 7. Summary

```text
Audit Logging Pattern

Trigger    → domain events, dispatched by every audited module, exactly like
              AgentArchived (Stage 2) and PredictionStored (Stage 5)
Listener    → lives inside Audit, never inside the emitting module
Scope        → Human-initiated, administratively-significant actions only —
                 not high-volume system data already recorded elsewhere
Failure       → logged, never blocking, never rolling back the primary action
```
