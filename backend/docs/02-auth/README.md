# SentinelX — Authentication & Identity Documentation

> **Status:** 🔒 **FROZEN** (Documentation Baseline v2.0)
> **Last Updated:** After closing the Authentication Design phase (9 complete sessions), and synchronized with the Backend Architecture Cross-Review (`Company` → `Organization`; `Team Management` and `Invitations` confirmed deferred — see `backend-architecture/adr/ADR-001-organization-naming.md` and `ADR-002-human-identity-baseline-update.md`)
> **Owner:** Backend / Security Architecture Team

---

## 1. Why does this folder exist?

This is not a description of a login page, and not a tutorial on JWT or Laravel Sanctum.

This is the **single official Source of Truth** for how SentinelX proves identity, verifies it on every request, and decides what an identity is allowed to do.

> **Documentation is the design guide, not an explanation of the code. We are not writing this documentation to explain the code — we are writing it so the code gets written from it.**

The goal: an engineer (human or Claude Code) should be able to spend two hours reading this folder and then write a week's worth of code that matches exactly the philosophy designed here — with zero improvisation and zero redesign during implementation.

> **If there is ever a conflict between the code and this document, this document is the source of truth — unless there is an officially documented update here.**

---

## 2. The Core Idea in One Sentence

> **Authentication answers "Who are you?" Authorization answers "What are you allowed to do?" Neither of them is the same thing as user management, and neither of them belongs to the business domain.**

Every file in this folder is a direct expansion of that one sentence.

---

## 3. Who Needs to Authenticate?

SentinelX is used by exactly three kinds of actors, and — this is the central architectural decision of the whole design — **each of them authenticates differently**:

```text
SentinelX Platform
        ┌────────────────────────────┐
        │      Human Users            │
        │      (Dashboard)            │
        └────────────────────────────┘
                    ▲
        ┌────────────────────────────┐
        │      AI Agents               │
        │      (SDK / API Key)         │
        └────────────────────────────┘
                    ▲
        ┌────────────────────────────┐
        │   Internal Services          │
        │ (Backend ↔ ML Service)       │
        └────────────────────────────┘
```

There is **no universal authentication mechanism**. A Human uses a password and a JWT. An Agent uses a long-lived API Key. An Internal Service uses a private network / shared secret. All three ultimately produce the exact same output object: an **Authenticated Identity**.

---

## 4. Folder Architecture

```text
backend/docs/02-auth/
        │
        ├── README.md                              ← you are here
        │
        ├── 01-overview.md                          ← Authentication Philosophy
        ├── 02-domain.md                             ← Identity Design
        ├── 03-authentication-flow.md                 ← Authentication Flows
        ├── 04-jwt.md                                  ← JWT Design
        ├── 05-api-keys.md                              ← API Key Design
        ├── 06-authorization.md                          ← Authorization Design
        ├── 07-security.md                                ← Security Hardening
        ├── 08-identity-lifecycle.md                        ← Organization & Identity Lifecycle
        ├── 09-implementation-roadmap.md                      ← Implementation Roadmap
        │
        ├── adr/                                     ← ADRs — the pivotal decisions and why
        │   ├── ADR-001-role-storage.md
        │   ├── ADR-002-api-key-design.md
        │   ├── ADR-003-jwt-claims.md
        │   ├── ADR-004-invitation-based-onboarding.md
        │   └── ADR-005-email-verified-at-column.md
        │
        ├── contracts/                               ← exact formats, for implementation
        │   ├── jwt-claims.md
        │   ├── api-key-format.md
        │   └── auth-errors.md
        │
        ├── diagrams/                                 ← visual SVG diagrams
        │   ├── erd/
        │   ├── sequence/
        │   ├── flow/
        │   └── state/
        │
        └── glossary.md
```

Every numbered file (`01-...` through `09-...`) is a **direct, faithful translation of one design session** — not a summary, not a reshuffle. This gives the documentation a clear story: reading the files in order reproduces the exact reasoning that produced the final design.

---

## 5. Recommended Reading Order

| # | File | What you'll learn |
|---|------|--------------------|
| 1 | [`01-overview.md`](./01-overview.md) | What authentication is (and isn't), and why it never touches permissions or user management |
| 2 | [`02-domain.md`](./02-domain.md) | What an Identity is, the three Identity types, and why Identity ≠ User |
| 3 | [`03-authentication-flow.md`](./03-authentication-flow.md) | The exact step-by-step flow for Human and Agent authentication |
| 4 | [`04-jwt.md`](./04-jwt.md) | What the JWT is for, what it contains, and — critically — what it must never contain |
| 5 | [`05-api-keys.md`](./05-api-keys.md) | How Agents authenticate with a long-lived credential |
| 6 | [`06-authorization.md`](./06-authorization.md) | RBAC for Humans, Capabilities for Agents, and how a decision is made |
| 7 | [`07-security.md`](./07-security.md) | The ten hardening principles applied across every layer |
| 8 | [`08-identity-lifecycle.md`](./08-identity-lifecycle.md) | How an Organization and its Owner come into existence (V1). Also documents the Invitation/Team Management design for a future version (see the scope banner at the top of the file) |
| 9 | [`09-implementation-roadmap.md`](./09-implementation-roadmap.md) | The actual build order, phase by phase, sprint by sprint |
| — | [`adr/`](./adr) | The five pivotal architectural decisions, with full reasoning and rejected alternatives |
| — | [`contracts/`](./contracts) | Exact JWT claim shape, API key format, and error response shape — implementation-ready |
| — | [`diagrams/`](./diagrams) | ERD, sequence diagrams, flow diagrams, and state diagrams |
| — | [`glossary.md`](./glossary.md) | Every term defined once, to prevent drift and repetition |

---

## 6. The Golden Chain

Every session, every diagram, and every decision in this folder ultimately reduces to one chain that must never be broken:

```text
Identity
    ↓
Credential
    ↓
Authentication
    ↓
Authenticated Identity
    ↓
Authorization
    ↓
Business Logic
    ↓
Audit
```

No layer skips ahead. No layer reaches backward. Business Logic never touches a password, a JWT, or an API Key — it only ever sees an **Authenticated Identity**.

---

## 7. Design Status

```text
Authentication Design
████████████████████████████ 100%

Authentication Philosophy         ✅ Frozen
Identity Design                    ✅ Frozen
Authentication Flows                ✅ Frozen
JWT Design                           ✅ Frozen
API Key Design                        ✅ Frozen
Authorization Design                   ✅ Frozen
Security Hardening                      ✅ Frozen
Organization & Identity Lifecycle        ✅ Frozen (V1: single-Owner only; Invitations deferred)
Implementation Roadmap                    ✅ Frozen
```

> **The authentication design is approved (Frozen) as of the end of Session 9.**
> Any change after this point must be driven by a **new business requirement** or a **new product version (V2)**, not just a rethink or optimization.

---

## 8. What We Deliberately Did NOT Do (V1 Scope)

To avoid over-engineering, these were **consciously excluded** from V1:

```text
❌ MFA (Multi-Factor Authentication)     ❌ SSO
❌ OAuth                                  ❌ Device Fingerprinting
❌ Geo Blocking                            ❌ Risk-based Authentication
❌ Adaptive Authentication                  ❌ Multiple API Keys per Agent
❌ Token Revocation / Blacklist              ❌ Multi-device Session Management
❌ Multi-Organization Membership               ❌ Agent Roles
❌ Team Management (multi-member orgs)          🟡 Invitations (designed, not built in V1)
```

> **Baseline v2.0 update:** per the Cross-Review resolution (`backend-architecture/adr/ADR-002-human-identity-baseline-update.md`), `Team Management` and `Invitations` — both fully designed in [`08-identity-lifecycle.md`](./08-identity-lifecycle.md) — are confirmed deferred. V1 ships with single-Owner Organizations only; the Role model (`Owner`, `Admin`, `Member`) remains in the baseline so no schema change is needed when these features ship.

Every one of these was discussed and rejected for a **specific reason**, not out of ignorance — details are inside the relevant numbered file or ADR. Most are explicitly earmarked as **Future Evolution**, not permanently rejected.
