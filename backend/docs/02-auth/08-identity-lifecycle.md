# 08 — Organization & Identity Lifecycle

> **Scope Status (Baseline v2.0):** The Organization creation and single-Owner registration flow described below is **V1 scope**. The Invitation mechanism and multi-member Team Management described in this file are **deferred to a future version** — see [`adr/ADR-004-invitation-based-onboarding.md`](./adr/ADR-004-invitation-based-onboarding.md) for the resolved scope. In V1, every Organization is provisioned with exactly one User — its Owner — and there is no in-product flow yet for that Owner to add teammates. The design below is preserved in full because it is the intended design for when Invitations ship; nothing here should be built against V1 unless explicitly marked otherwise.

> Source: the session originally added as "Identity Lifecycle," later renamed and finalized as **Organization & Identity Lifecycle** — the true final session of the Authentication design series (Session 9, Implementation Roadmap, was renumbered to come after it).
> This file answers a question every prior session quietly assumed: **"The person already exists... but how did they get here in the first place?"**

---

## 1. Why This Session Is Independent

All prior sessions talked as if "the person is already there." But how did they actually arrive? This is a meaningful architectural question, and it deserves its own session because it's not a detail — it's part of the **Authentication & Identity Lifecycle**.

More precisely, this session should really be understood as covering the relationship between:

```text
The Organization
The User
Invitations
Roles
Joining and Leaving (Membership Lifecycle)
```

Which is why the more accurate name for it is:

> **Organization & Identity Lifecycle**

This session is a natural end to the Authentication series, and a natural beginning for the Backend Design series that follows it.

---

## 2. The First Entity That Is Ever Born

Before Ahmed can log in, there must already be a Organization. All along, we talked about `Users` — but the truth is, the very first entity born in the system is:

> **Organization**

Not `User`.

---

## 3. The Beginning of the Story

Ahmed hears about SentinelX. He visits the site and finds a button:

```text
Create Organization
```

He clicks it. The system asks for:

```text
Organization Name
Organization Email
Owner Name
Password
```

Note: **we are not registering a User. We are creating an Organization.**

### What Happens Internally

```text
Create Organization
    ↓
Create Owner User
    ↓
Link Owner → Organization
    ↓
Send Verification Email
```

So the very first user ever created inside any Organization is **always**:

> **Owner**

This is a fixed rule.

---

## 4. Growing the Team 🟡 (Deferred to a Future Version)

A few days later, Ahmed wants to add an engineer. Does the engineer go and `Register`?

**No.** Because `Register`, in our system, specifically means:

> **Creating a new Organization.**

Not joining an existing one — a crucial difference.

### So What Actually Happens?

The Owner opens the Dashboard and clicks:

```text
Invite Team Member
```

Then enters:

```text
Email
Role
```

For example: `mohamed@organization.com`, `Admin`.

The system creates an **Invitation**, and sends an email.

Mohamed opens the email and clicks **Accept Invitation**, then fills in:

```text
Name
Password
```

...and becomes a user inside the organization.

Note the difference: Mohamed never registered. He performed **Join Organization** — an entirely different operation from `Register`.

### Members Follow the Same Path

```text
Owner
    ↓
Invite
    ↓
Accept
    ↓
Set Password
    ↓
Done
```

---

## 5. Can an Admin Send Invitations?

A decision that must be made explicitly. For the current project:

**Owner:**
```text
✔ Invite Users
✔ Remove Users
✔ Change Roles
✔ Delete Organization
```

**Admin:** manages day-to-day operations, but **cannot** change the organization's own identity — because these are sovereign-level privileges reserved for the Owner.

---

## 6. The Full Onboarding Flow

```text
Organization Creation
    ↓
Owner Created
    ↓
Owner Login
    ↓
Invite User
    ↓
Invitation Sent
    ↓
User Accepts Invitation
    ↓
Set Password
    ↓
Account Activated
    ↓
Login
```

---

## 7. What If Someone Registers With an Email That's Already Invited?

We deliberately prevent this. If an email has a **Pending Invitation**, we don't let it create a new Organization. Instead:

> **"You already have a pending invitation."**

This avoids confusion between two entirely different intents.

---

## 8. What If an Invitation Expires?

We provide:

```text
Resend Invitation
```

That's it.

---

## 9. What If the Owner Changes Their Mind?

The Owner sent an invitation, then decides against it. We provide:

```text
Cancel Invitation
```

---

## 10. The Invitation Lifecycle

An Invitation is not simply "sent or not sent" — it has a full state machine:

```text
Pending
    ↓
Accepted
    ↓
Expired
    ↓
Cancelled
```

See the visual state diagram in [`diagrams/state/`](./diagrams/state).

---

## 11. What Happens If a Member Leaves the Organization?

Is the Account deleted? **No** — an elegant architectural point. The Account remains, but its **link to the organization** is removed, because the same person might work for a different organization a month later.

---

## 12. A Concept Worth Introducing: Membership

We are not really dealing with:

```text
User
```

alone. We're dealing with:

```text
Membership
```

One of the most beautiful SaaS concepts. That is: `User` is a **global entity**, but:

```text
Organization
    ↓
Membership
    ↓
Role
```

The Role does not live on the User — the Role lives **inside the organization relationship**.

### Why This Matters

Imagine Mohamed could be `Owner` in his own personal organization and, at the same time, `Member` in a different organization — the same User, but a different Membership per organization.

---

## 13. Do We Implement This in V1?

**No** — and this is an important scoping decision. We keep the idea on the shelf, because while it's architecturally correct, it would add unneeded complexity to the MVP.

In V1, every User belongs to exactly one organization, so having `organization_id` and `role` directly on the User is entirely sufficient.

However, from day one, we officially name this part of the documentation:

> **Future Evolution: Multi-Organization Membership**

so that if the project grows, we know exactly what to build — without having to redesign the system from scratch.

---

## 14. Removing a User

If Ahmed decides to remove an Admin, he is **not deleting the User**. He is removing the User's relationship with the organization. In the MVP, since the relationship is direct:

```text
User
    ↓
Inactive
or
Removed from Organization
```

depending on the policy adopted during implementation — but the data itself is never permanently destroyed.

---

## 15. Changing a Role

The Owner opens `Team → Change Role → Save`. The system updates `User.role`. If the person is already logged in, they will notice the change **on their next request**, because we agreed — in Session 6 — that Role is always read from the database, never from the JWT.

---

## 16. Can the Owner Remove Themselves?

**No.** Doing so could leave the organization without an owner. So there's a simple rule:

> **There must always be at least one Owner per Organization.**

If we later want to support ownership transfer, we would build an independent flow:

```text
Transfer Ownership
    ↓
Confirm
    ↓
New Owner
    ↓
Old Owner becomes Admin
```

But this is outside the MVP scope.

---

## 17. The Complete Flow

```text
Create Organization
    ↓
Owner Created
    ↓
Email Verification
    ↓
Owner Login
    ↓
Invite User
    ↓
Invitation Sent
    ↓
Invitation Accepted
    ↓
User Created
    ↓
Login
    ↓
Team Management
    ↓
Role Changes
    ↓
Member Removal
```

---

## 18. Principles Established in This Session

```text
✔ Registration creates an Organization.
✔ The first user is always the Owner.
✔ Team members never register directly.
✔ Team members join through Invitations.
✔ Invitations create trust before accounts.
✔ Roles are assigned by the Owner.
✔ Every Organization must always have an Owner.
✔ Users are deactivated or removed, not hard-deleted.
✔ Roles are loaded from the database, not from JWT.
```

---

## 19. The Most Important Decision in the Entire Series

> Shifting the mental model from **User Registration** to **Organization Onboarding**.

This is a huge shift, because we are not building a consumer (B2C) app — we are building a B2B SaaS platform for organizations. Our real client is the organization, and Users are simply members inside it.

Once this shift is made, every flow becomes logically consistent:

- The Owner is the one who begins the journey.
- The team joins via Invitations.
- Roles are managed from within the organization.
- Identity has been tied to the Organization from the very first moment.

This is, in our view, the final piece of the Authentication design puzzle, and after it, we can move confidently into Backend Architecture, knowing the foundation is solid.

---

## 20. Relevant Contract & ADR References

- The reasoning behind Invitation-based onboarding instead of open public registration: [`adr/ADR-004-invitation-based-onboarding.md`](./adr/ADR-004-invitation-based-onboarding.md).
- The Invitation state diagram: [`diagrams/state/`](./diagrams/state).
- The "Invite User" sequence diagram: [`diagrams/sequence/`](./diagrams/sequence).
