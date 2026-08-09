# ADR-002: No `Reopen` Endpoint Is Built in V1 (Resolves a Real Conflict Between Two Frozen Documents)

| | |
|---|---|
| **Status** | ✅ Accepted (Documentation Conflict, Resolved by Deferring to the More Specific Frozen Source) |
| **Scope** | Alert Module |
| **Affects** | `alerts.status` state machine, the public API surface of this module |

---

## Context

Two frozen documents disagree:

```text
docs/backend/backend-architecture/04-module-responsibilities.md §7
    "Responsible For: Create Alert, Update Status, Resolve, Reopen, Listing."

docs.zip/09-api-reference/05-ALERTS_API.md
    Exposes exactly four routes: GET /alerts, GET /alerts/{id},
    PATCH .../acknowledge, PATCH .../resolve.
    No reopen route of any kind.
```

Per this documentation series' own operating principle (stated in every module's `README.md`): *"If there is ever a conflict between this folder and `docs.zip`, `docs.zip` wins."* But this is a conflict **between two files both inside the frozen baseline**, not between this new folder and `docs.zip` — so that rule doesn't directly resolve it, and picking a side requires actual judgment, made explicit here rather than silently.

---

## Decision

**V1 builds only the four endpoints `ALERTS_API.md` actually specifies.** No `PATCH /alerts/{id}/reopen` route, no `RESOLVED → OPEN` (or `ACKNOWLEDGED → OPEN`) transition, exists anywhere in this Sprint's implementation. `04-module-responsibilities.md`'s mention of `Reopen` is treated as either an earlier design intention that didn't make it into the final frozen API contract, or a forward-looking note about a future capability — this ADR does not attempt to guess which, only to record that the two documents disagree and that this module follows the more specific, endpoint-level source.

---

## Rationale

### Why trust the API reference document over the module-responsibilities document?
`04-module-responsibilities.md` is a high-level, prose description of what a module is *generally* responsible for — written during architecture design, before the exact endpoint list was finalized. `ALERTS_API.md` is the actual, specific, endpoint-by-endpoint public contract — the artifact SDK integrators and the Dashboard frontend are meant to build against. When a general description and a specific contract disagree, building against the specific contract is the lower-risk choice: shipping a route the specific contract never promised is easy to add later; shipping a route the specific contract explicitly omitted, only to find out it was intentionally left out (e.g., because "reopen" was deliberately rejected during a later design pass this documentation snapshot doesn't capture), is harder to walk back once integrators depend on it.

### Why not just build it anyway — what's the harm in an extra endpoint?
Because doing so would be exactly the kind of silent, undocumented expansion this entire documentation series exists to prevent. If `Reopen` is genuinely wanted, the correct process — per this project's own stated discipline — is to resolve the conflict explicitly (which this ADR does) and, if the decision changes, update `ALERTS_API.md` itself, not to quietly implement a capability one frozen document mentions and another omits.

### Does this cause any real functional gap?
Minimal. Per [`02-domain.md`](../02-domain.md) §6, `RESOLVED` being terminal is independently consistent with `enums.md` §9's own reasoning about why `AlertStatus` deliberately has no `ARCHIVED` value. If an Alert is genuinely resolved incorrectly, the practical V1 remedy is that its underlying Prediction and Observation remain fully visible and auditable regardless of the Alert's terminal status — nothing about the underlying security record disappears, only the Alert's own workflow state stops changing.

---

## Rejected Alternatives

| Alternative | Reason for Rejection |
|-------------|------------------------|
| Build the reopen endpoint anyway, since `module-responsibilities.md` mentions it | Silently expands the API surface beyond what the specific, frozen `ALERTS_API.md` contract promises — exactly the undocumented-expansion risk this series exists to prevent |
| Treat this as blocking and refuse to proceed with Stage 5 until the conflict is resolved by the documentation owners | Overly conservative for a minor, low-stakes discrepancy with a defensible resolution available; blocking an entire Sprint over one omitted verb is disproportionate |
| Silently ignore the discrepancy without recording it anywhere | Defeats the purpose of a documentation-first workflow — a future engineer (or Claude Code) re-reading `04-module-responsibilities.md` alone would reasonably expect a reopen endpoint to exist and be confused when it doesn't |

---

## Consequences

- ✅ The implemented API surface matches the actual, specific, frozen `ALERTS_API.md` contract exactly — no undocumented routes.
- ✅ The conflict is now visible and traceable, rather than silently resolved in one direction with no record.
- ⚠️ If `Reopen` genuinely is a wanted V1 capability, this ADR is the natural place to revisit that decision — and `ALERTS_API.md` itself should be updated first, before this module's implementation changes to match.
