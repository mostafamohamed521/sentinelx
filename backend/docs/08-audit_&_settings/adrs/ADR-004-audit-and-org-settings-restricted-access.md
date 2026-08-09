# ADR-004: Audit/Security Logs Are Owner+Admin Only; Organization Settings Updates Are Owner Only — Breaking the "Everyone Equal" Precedent, Deliberately

| | |
|---|---|
| **Status** | ✅ Accepted (Engineering Default — the First Asymmetric Role Decision in This Series) |
| **Scope** | Audit Module, Organization Module |
| **Affects** | Role checks on `PATCH /organization`, `GET /audit-logs`, `GET /audit-logs/{id}`, `GET /security-logs` |

---

## Context

Every prior gap-filled Role decision in this series — [`docs/backend/alert/adr/ADR-003`](../../06-alert/adr/ADR-003-all-roles-can-act-on-alerts.md), [`docs/backend/dashboard/adr/ADR-003`](../../07-dashboard/adr/ADR-003-all-roles-can-view-dashboard.md) — landed on full symmetry across `Owner`/`Admin`/`Member`, justified by a specific, mechanical argument: the data or action in question was already fully reconstructable by a `Member` through some other endpoint they already had access to, so restricting it added friction without adding security. That mechanical argument genuinely does not apply to either decision in this ADR — see [`07-authorization.md`](../07-authorization.md) §1 for the full comparison.

---

## Decision

```text
PATCH /organization        → Owner only
GET  /audit-logs (+ {id})    → Owner, Admin (Member excluded)
GET  /security-logs            → Owner, Admin (Member excluded)
```

---

## Rationale

### Why does Audit/Security Log visibility not qualify for the "everyone equal" precedent?
Because the precedent's entire justification was "a Member could already see this elsewhere" — and for Audit Logs, that's false. No other endpoint in the entire backend exposes *"which team member performed which administrative action, and when."* This is new, previously-inaccessible information about *other Users'* behavior, not a re-presentation of security findings (which genuinely are already visible to every Role, and rightly so — see [`docs/backend/observation/06-authorization.md`](../../04-observation/06-authorization.md) §2's original reasoning). Extending "view results" (Member's own frozen definition) to also mean "view an accountability trail of colleagues' administrative actions" is a real stretch of that definition, not a natural reading of it.

### Why is Organization Settings' update action treated differently from, say, Alert's acknowledge/resolve (which landed on "everyone")?
Because the two Role decisions rest on different textual anchors. Alert's symmetry decision rested on `Member`'s definition, *"works with results"* — acknowledging/resolving a security incident squarely fits that description. Renaming an Organization doesn't fit any part of `Member`'s definition; it fits `Owner`'s definition far better (*"the organization's founder/owner"*), and echoes the one piece of genuinely frozen text from early in this series (Stage 2's real precedent: *"day-to-day platform operation (create Agents, rotate API Keys...)"* attributed specifically to Owner-level activity, at a time when the Role model only had two tiers).

### Why include `Admin` for Audit/Security Logs but not for Organization Settings?
A judgment call, made explicit rather than hidden: `Admin`'s frozen definition — *"manages the platform's day-to-day operation"* — plausibly extends to reviewing team activity as routine operational oversight, but doesn't obviously extend to unilaterally renaming the Organization itself, which feels closer to a founder-level decision than a day-to-day one. This line is genuinely debatable, and is recorded as a considered judgment, not a mechanically-derived certainty — exactly the honesty this entire ADR is trying to model.

---

## Rejected Alternatives

| Alternative | Reason for Rejection |
|-------------|------------------------|
| Extend the "everyone equal" precedent to Audit/Security Logs, for consistency with Stages 5/6 | The mechanical justification underlying that precedent (data already accessible elsewhere) doesn't hold here — applying the same conclusion without the same supporting argument would be consistency for its own sake, not genuine reasoning |
| Restrict Organization Settings viewing (`GET /organization`), not just updating, to Owner/Admin | No real justification — basic org info (name, status) is not sensitive, and every Role already needs to know which Organization they belong to; restricting the read would be pure friction |
| Restrict Audit/Security Logs to Owner only, excluding Admin entirely | `Admin`'s own frozen definition of managing day-to-day operations plausibly includes this kind of oversight; excluding Admin entirely felt like an unjustified extra restriction with no clearer textual support than including them |

---

## Consequences

- ✅ The one place in this Sprint's design where a Role restriction has genuine substance (new information, not a re-presentation) is treated with a different, more restrictive default than the "everyone equal" pattern — deliberately, not by oversight.
- ✅ Every reasoning step is recorded, so a future reviewer who disagrees with the Owner/Admin split (versus, say, Owner-only, or a fully separate Role) has a clear, specific point to challenge rather than an unexplained rule.
- ⚠️ This is the most subjective Role decision in the entire series — more so than any prior gap-fill — precisely because Audit/Security data doesn't map cleanly onto any of the three Role definitions the way viewing Observations or Alerts did. Expect this to be one of the first things revisited if real usage reveals the line was drawn in the wrong place.
