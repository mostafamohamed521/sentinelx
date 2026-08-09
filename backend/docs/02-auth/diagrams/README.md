# Diagrams

All diagrams are in **SVG** format. They are organized into four categories, exactly as scoped during the architecture-planning session: enough to be useful, not so many that they become a maintenance burden.

## `erd/`

| File | Description |
|------|-------------|
| [`auth-domain-erd.svg`](./erd/auth-domain-erd.svg) | The Authentication-domain entities: `Organization`, `User`, `Agent`, `API Key`, and `Invitation` (🟡 future version — not built in V1), and their relationships. This is a focused view — for the full platform database ERD (Observations, Predictions, Alerts, etc.), see the `database/` documentation instead. |

## `sequence/`

| File | Description | Source |
|------|-------------|--------|
| [`login.svg`](./sequence/login.svg) | Human login: password verification through JWT issuance | [`03-authentication-flow.md`](../03-authentication-flow.md), [`04-jwt.md`](../04-jwt.md) |
| [`invite-user.svg`](./sequence/invite-user.svg) | 🟡 **Future Version.** Owner invites a team member, through acceptance and account activation | [`08-identity-lifecycle.md`](../08-identity-lifecycle.md) |
| [`agent-observation-authentication.svg`](./sequence/agent-observation-authentication.svg) | An Agent authenticating via API Key on every request, with no session | [`03-authentication-flow.md`](../03-authentication-flow.md), [`05-api-keys.md`](../05-api-keys.md) |
| [`jwt-verification.svg`](./sequence/jwt-verification.svg) | Verifying an already-issued JWT on a subsequent request | [`04-jwt.md`](../04-jwt.md) |

## `flow/`

| File | Description |
|------|-------------|
| [`authentication-pipeline.svg`](./flow/authentication-pipeline.svg) | The single, high-level pipeline every request passes through — showing how the Human and Agent branches converge into one Authenticated Identity, then Authorization, then Business Logic, then the Audit Log |

## `state/`

| File | Description |
|------|-------------|
| [`invitation-state.svg`](./state/invitation-state.svg) | 🟡 **Future Version.** `PENDING → ACCEPTED / EXPIRED / CANCELLED` |
| [`api-key-state.svg`](./state/api-key-state.svg) | `ACTIVE → REVOKED` |
| [`human-identity-state.svg`](./state/human-identity-state.svg) | `ACTIVE → DISABLED`, matching `users.status` exactly — `DISABLED` means administratively deactivated only; it does not represent an unverified account (see [`adr/ADR-005-email-verified-at-column.md`](../adr/ADR-005-email-verified-at-column.md)) |
| [`agent-identity-state.svg`](./state/agent-identity-state.svg) | `ACTIVE → ARCHIVED` (matches `agents.status` exactly) |

---

All diagrams are built directly from the decisions documented in the numbered files and in [`../adr/`](../adr). Any change to the design must be reflected here as well, to keep the documentation internally consistent.
