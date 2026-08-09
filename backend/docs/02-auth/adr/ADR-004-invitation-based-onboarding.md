# ADR-004: Team Members Join via Invitation, Never Through Public Registration

| | |
|---|---|
| **Status** | ✅ Design Accepted (Frozen) — 🟡 **Implementation Deferred to a Future Version** (see `backend-architecture/adr/ADR-002-human-identity-baseline-update.md`) |
| **Session** | Organization & Identity Lifecycle |
| **Affects** | The onboarding flow, the meaning of `Register`, and the entire Invitation subsystem |

---

## Scope Note (Baseline v2.0)

The design decision below — that team members must join via Invitation rather than public registration — remains the correct target design and is **not being reversed**. However, following the Cross-Review resolution, `Team Management` and `Invitations` are **not part of V1**. In V1, every Organization is provisioned with exactly one User (its Owner), and none of the flows described below are built yet. This ADR is preserved in full so the Invitation subsystem can be implemented directly from this design once Team Management ships, without needing to be redesigned.

---

## Context

Once "Register" was established as meaning *"create a new Organization"* (see [`08-identity-lifecycle.md`](../08-identity-lifecycle.md)), a mechanism was needed for how additional team members (beyond the first Owner) actually get into that Organization. The two options considered were: letting anyone self-register and somehow attach themselves to an existing organization, versus requiring an existing member to explicitly invite them.

---

## Decision

**Team members never register directly.** They can only join an Organization through an **Invitation** issued by an existing Owner (or, depending on policy, an Admin).

```text
Owner
    ↓
Invite (email + role)
    ↓
Invitation created (status: Pending)
    ↓
Email sent
    ↓
Invitee clicks Accept Invitation
    ↓
Invitee sets Name + Password
    ↓
User created, attached to Organization, Membership activated
```

The Invitation itself has a full lifecycle: `Pending → Accepted / Expired / Cancelled`.

---

## Rationale

### `Register` Already Has a Fixed, Different Meaning
`Register`, in this system, unambiguously means "create a new Organization" (see [`08-identity-lifecycle.md`](../08-identity-lifecycle.md)). Allowing a second, different meaning for the same action — "join an existing organization" — would overload the term and confuse both the implementation and the user experience.

### Invitations Establish Trust Before an Account Exists
An Invitation lets the Owner explicitly vouch for who should be allowed in, and with what Role, before any account is created. This is the correct model for a B2B platform where the Organization — not the individual — is the real customer relationship.

### It Prevents Accidental or Malicious Self-Attachment
If self-registration into an existing organization were allowed (e.g., by matching an email domain), it would open the door to unauthorized users joining a organization they have no real relationship with. Requiring an explicit Owner-issued Invitation closes this gap entirely.

### The User Is Created at Acceptance Time, Not at Invitation Time
This is a deliberate secondary decision within this ADR: creating the `User` record only when the invitation is accepted (not when it's sent) keeps the data model clean — there is no "ghost user" sitting in the database for an invitation that might expire or be cancelled.

### What Happens If the Invited Email Already Has an Account?
In V1, this case is explicitly rejected with a clear message rather than silently handled, because supporting a single User belonging to multiple Organizations is a larger architectural change (see the *Future Evolution: Multi-Organization Membership* note in [`08-identity-lifecycle.md`](../08-identity-lifecycle.md)) that is deliberately out of scope for the MVP.

---

## Rejected Alternatives

| Alternative | Reason for Rejection |
|-------------|------------------------|
| Public registration with a "join by organization code" step | Weakens the Owner's control over exactly who enters the organization, and introduces a shareable secret (the code) that behaves like a weak, hard-to-rotate credential |
| Auto-join based on matching email domain | Silently trusts an unverifiable signal (the email domain) as proof of organizational membership — a real security and correctness risk |
| Create the User record immediately when the Invitation is sent | Leaves an inactive, password-less "ghost" User in the database for every pending or expired invitation, complicating uniqueness constraints and cleanup |

---

## Consequences

- ✅ The Owner retains full, explicit control over who joins the Organization and with what Role.
- ✅ No orphaned or inactive User records are created for invitations that are never accepted.
- ✅ The system has a clean, auditable trail of every invitation issued, accepted, expired, or cancelled — directly serving the "Audit Everything Important" principle from [`07-security.md`](../07-security.md).
- ⚠️ Requires a dedicated `Invitation` entity with its own state machine (see [`diagrams/state/`](../diagrams/state)), rather than reusing the `User` table for pending members.
- ⚠️ In V1, an email address that already has an active account cannot accept a new invitation to a different Organization — this is a known, deliberate MVP limitation, not an oversight.
