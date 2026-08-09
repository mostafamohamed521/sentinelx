# ADR-003: Owner, Admin, and Member All Have Identical Access to Every Alert Action

| | |
|---|---|
| **Status** | ✅ Accepted (Engineering Default — Resolves a Genuine Gap in Frozen Documentation) |
| **Scope** | Alert Module |
| **Affects** | Role checks on all four Alert endpoints |

---

## Context

No frozen document provides a per-endpoint Role matrix for Alerts. [`02-auth/06-authorization.md`](../../02-auth/02-auth/06-authorization.md) §5 lists `View Alerts` only as one illustrative example within a general "Human Permissions" list, without assigning it to a specific Role, and says nothing about acknowledge or resolve at all. This is a real gap, structurally identical to the severity-threshold gap resolved in [`ADR-001-severity-threshold-mapping.md`](./ADR-001-severity-threshold-mapping.md) — a genuine decision this documentation must make explicitly, rather than one this ADR is overriding.

---

## Decision

`Owner`, `Admin`, and `Member` all have identical access to `GET /alerts`, `GET /alerts/{id}`, `PATCH .../acknowledge`, and `PATCH .../resolve` — no Role-based restriction exists anywhere in this module.

---

## Rationale

### Why treat this as symmetric across all three Roles, when Agent management (Stage 2) restricted mutations to Owner only?
Because the two situations aren't actually analogous, and the frozen text supports treating them differently. Stage 2's Owner-only restriction was backed by real frozen language existing at the time of that design (*"day-to-day platform operation (create Agents, rotate API Keys...)"* attributed to Owner). No equivalent language exists anywhere for Alert actions. In the *absence* of specific guidance, the right anchor is the general Role definitions themselves — and [`02-auth/06-authorization.md`](../../02-auth/02-auth/06-authorization.md) §7 defines `Member` as: **"Views results and works with them."** Acknowledging and resolving a security Alert is the single clearest instance of "working with a result" anywhere in this platform's feature set — it is not comparable to creating or destroying an Agent, which is closer to infrastructure/account administration.

### Wouldn't restricting Alert-handling to Owner/Admin be the "safer" default?
Not for this specific platform's actual purpose. SentinelX exists so a security team can respond to incidents; artificially gating incident response behind an administrative Role tier would work directly against the product's core value, for no documented reason. [`docs/backend/observation/06-authorization.md`](../../04-observation/06-authorization.md) §2 already established the same reasoning for viewing Observations: *"Viewing security history is not an administrative privilege; it is the core value the platform provides to every seat in the Organization."* Extending that logic from *viewing* Observations to *acting on* Alerts is a natural, consistent continuation, not a new precedent.

### Why is this still an ADR and not just a line in `04-authorization.md`?
Because, like the severity-threshold gap, this decision materially shapes real behavior (who can act on a security incident) and has no frozen backing — it deserves the same visibility and the same invitation for reconsideration as any other genuinely open decision this series has had to make, not a quiet default buried in prose.

---

## Rejected Alternatives

| Alternative | Reason for Rejection |
|-------------|------------------------|
| Restrict acknowledge/resolve to `Owner`/`Admin` only, `Member` view-only | No frozen text supports this restriction, and it works against the platform's own stated purpose of enabling incident response; would need to invent a justification not present anywhere in the documentation |
| Restrict all four actions (including viewing) to `Owner`/`Admin` only | Even more restrictive, with even less textual support — directly contradicts `Member`'s own frozen definition, which explicitly includes "views results" |
| Leave the Role check unspecified/TODO for implementation to decide ad hoc | Exactly the outcome this documentation series exists to prevent — an unspecified authorization rule is a security-relevant gap, not a minor detail safe to leave to whoever happens to implement this Sprint |

---

## Consequences

- ✅ Every seat in an Organization can fully participate in incident response — consistent with the product's actual purpose.
- ✅ The authorization model for this module is maximally simple: one Role check ("is this an authenticated Human, any Role") rather than four separate per-endpoint Role gates to get right and keep consistent.
- ⚠️ If a future, real requirement emerges for tiered Alert-handling permissions (e.g., only `Owner`/`Admin` may resolve *`CRITICAL`* Alerts specifically), this ADR is the place to record that change — it is not anticipated by anything in the current frozen documentation set, and should not be speculatively built now.
