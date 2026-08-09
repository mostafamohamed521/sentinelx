# Enums

> Every enum value in the SentinelX database. Stored as **clear UPPERCASE strings**, not numbers — for readability, easier debugging, and a perfect fit for the current project scale.
> See [`naming-conventions.md`](../architecture/naming-conventions.md#6-enums) for the naming rule.

---

## 1. `OrganizationStatus` — table `organizations.status`

| Value | Description |
|-------|-------------|
| `ACTIVE` | The organization is active and using the platform normally |
| `SUSPENDED` | The organization is suspended (replaces physical deletion) |

**Deliberately not added in V1:** `ARCHIVED`, `PENDING`.

---

## 2. `UserRole` — table `users.role`

| Value | Description |
|-------|-------------|
| `OWNER` | The primary owner of the organization account |
| `ADMIN` | Manages day-to-day platform operation; cannot alter the organization's own identity |
| `MEMBER` | A regular member within the organization |

**Deliberately not added in V1:** `VIEWER` — to avoid over-engineering before a full RBAC system is built. `ADMIN` is included per [`backend-architecture/adr/ADR-002-human-identity-baseline-update.md`](../../00-backend_architecture/adr/ADR-002-human-identity-baseline-update.md), which keeps the Role model future-proofed for Team Management ahead of its V1 build-out.

---

## 3. `UserStatus` — table `users.status`

| Value | Description |
|-------|-------------|
| `ACTIVE` | The user is active and able to log in |
| `DISABLED` | The user is disabled (replaces deletion) |

---

## 4. `AgentStatus` — table `agents.status`

| Value | Description |
|-------|-------------|
| `ACTIVE` | The agent is running and sending Observations normally |
| `ARCHIVED` | The agent has been archived (the real lifecycle — replaces deletion) |

**Deliberately not added in V1:** `DISABLED` — because `ARCHIVED` already reflects a real business action (Archive Agent).

---

## 5. `ApiKeyStatus` — table `api_keys.status`

| Value | Description |
|-------|-------------|
| `ACTIVE` | The key is active and can be used for authentication |
| `REVOKED` | The key has been revoked (kept as an audit record, never deleted) |

**Business Rule:** only one `ACTIVE` key per Agent at any time (enforced at the application layer).

---

## 6. `AnalysisStatus` — table `observations.analysis_status`

| Value | Description |
|-------|-------------|
| `PENDING` | The Observation has arrived and analysis hasn't started yet |
| `PROCESSING` | ML is currently analyzing the Observation |
| `COMPLETED` | Analysis finished successfully, and a Prediction exists |
| `FAILED` | Analysis failed |

**Critical usage:** the Worker continuously queries `WHERE analysis_status = 'PENDING' ORDER BY received_at ASC` — this is why a dedicated index exists for this column (see [`indexes.md`](./indexes.md)).

---

## 7. `Verdict` — table `predictions.verdict`

| Value | Description |
|-------|-------------|
| `SAFE` | The event is safe — will never produce an Alert |
| `SUSPICIOUS` | The event is suspicious |
| `MALICIOUS` | The event is malicious/harmful |

**Performance note:** deliberately no index on this column — low cardinality (only 3 values), so the benefit of an index would be very limited.

---

## 8. `Severity` — table `alerts.severity`

| Value | Description |
|-------|-------------|
| `LOW` | Low severity |
| `MEDIUM` | Medium severity |
| `HIGH` | High severity |
| `CRITICAL` | Critical severity |

**Why separate from `risk_score` (numeric, in Predictions)?** Because users think in colors/levels, not raw numbers, when making an operational decision.

---

## 9. `AlertStatus` — table `alerts.status`

| Value | Description |
|-------|-------------|
| `OPEN` | The alert is new and hasn't been reviewed |
| `ACKNOWLEDGED` | The alert has been seen and is being handled |
| `RESOLVED` | The alert has been resolved |

**Deliberately not added:** `ARCHIVED` — because archiving is a storage strategy, not a business state.

---

## 10. Complete Summary of All Enums

```text
OrganizationStatus → ACTIVE, SUSPENDED
UserRole           → OWNER, ADMIN, MEMBER
UserStatus          → ACTIVE, DISABLED
AgentStatus          → ACTIVE, ARCHIVED
ApiKeyStatus          → ACTIVE, REVOKED
AnalysisStatus          → PENDING, PROCESSING, COMPLETED, FAILED
Verdict                   → SAFE, SUSPICIOUS, MALICIOUS
Severity                    → LOW, MEDIUM, HIGH, CRITICAL
AlertStatus                   → OPEN, ACKNOWLEDGED, RESOLVED
```
