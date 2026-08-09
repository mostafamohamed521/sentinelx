# Glossary

> Every term used across this documentation, defined exactly once. If a term isn't here, it isn't an official concept in this system yet.

---

**Identity**
Any entity capable of independently interacting with the system. The three types are Human, Agent, and Internal Service. See [`02-domain.md`](./02-domain.md).

**Principal**
A synonym used occasionally for a Security Identity (particularly the Agent, described as an "Identity + Security Principal" — see [`02-domain.md`](./02-domain.md)) — an entity that can independently prove itself to the system.

**Credential**
The means by which an Identity proves itself. Password for Humans, API Key for Agents, Shared Secret for Internal Services. A Credential is an attribute of an Identity — it is never the Identity itself. See [`02-domain.md`](./02-domain.md).

**Authentication**
The process of verifying identity — answering "Who are you?" Never responsible for permissions or for user management. See [`01-overview.md`](./01-overview.md).

**Authenticated Identity**
The single output object produced by any successful authentication flow (Human or Agent), containing only an ID, a type, and a organization association. This is the only thing every downstream layer (Authorization, Business Logic) ever interacts with — never the raw Password, JWT, or API Key. See [`03-authentication-flow.md`](./03-authentication-flow.md).

**Authorization**
The process of determining whether an Authenticated Identity is allowed to perform a specific action on a specific resource. Answers "What are you allowed to do?" See [`06-authorization.md`](./06-authorization.md).

**Role**
A named grouping of permissions assigned to a Human Identity within an Organization: `Owner`, `Admin`, or `Member`. Always loaded from the database, never embedded in the JWT. See [`06-authorization.md`](./06-authorization.md) and [`adr/ADR-001-role-storage.md`](./adr/ADR-001-role-storage.md).

**Permission**
A specific, granular action a Role is allowed to perform (e.g., "Create Agent", "View Alerts"). See [`06-authorization.md`](./06-authorization.md).

**Capability**
The Agent-specific equivalent of a Permission. Agents do not have Roles — they have a fixed, minimal set of Capabilities (in V1, exactly one: `Submit Observation`). See [`06-authorization.md`](./06-authorization.md).

**Owner**
The Role automatically assigned to the very first User created within an Organization. Holds sovereign-level privileges (invite/remove users, change roles, delete the organization). Every Organization must always have at least one Owner. See [`08-identity-lifecycle.md`](./08-identity-lifecycle.md).

**Admin**
A Role that manages day-to-day platform operation but cannot alter the Organization's own identity (e.g., cannot delete the organization). See [`06-authorization.md`](./06-authorization.md) and [`08-identity-lifecycle.md`](./08-identity-lifecycle.md).

**Member**
A Role that views results and works within the platform without administrative privileges. See [`06-authorization.md`](./06-authorization.md).

**Agent**
An AI entity that is the platform's real client — an Identity + Security Principal that authenticates via API Key, has no password and no login screen, and is stateless (every request re-authenticates independently). See [`02-domain.md`](./02-domain.md).

**Organization (Organization)**
The root tenant entity of the platform, and the true subject of registration. `Register` always means "create a new Organization" — never "create a personal account." See [`08-identity-lifecycle.md`](./08-identity-lifecycle.md).

**Observation**
The security event an Agent submits to the platform. Referenced here only insofar as it is the target action authorized under the Agent's `Submit Observation` Capability — full detail lives in the Database documentation, not here.

**API Key**
A long-lived credential belonging to an Agent, generated once, displayed once, and stored only as a hash. Used to authenticate every Agent request independently (no session). See [`05-api-keys.md`](./05-api-keys.md) and [`contracts/api-key-format.md`](./contracts/api-key-format.md).

**JWT (JSON Web Token)**
A short-lived, stateless proof that a Human Identity successfully authenticated. Carries only Identifiers (Identity ID, Identity Type, Organization ID, Issued At, Expires At) — never business data such as Name, Role, or Permissions. See [`04-jwt.md`](./04-jwt.md) and [`contracts/jwt-claims.md`](./contracts/jwt-claims.md).

**Session**
In the context of a Human Identity, the logical continuity represented by a valid JWT — the system "remembering" a previously authenticated Human across multiple requests. Agents have no concept of Session; they are stateless. See [`01-overview.md`](./01-overview.md) and [`03-authentication-flow.md`](./03-authentication-flow.md).

**Verification**
The check confirming that a previously established Identity is still valid at the moment of a new request (e.g., signature and expiration checks on a JWT, or hash comparison for an API Key). See [`01-overview.md`](./01-overview.md).

**Email Verification**
The one-time confirmation that a newly registered Human's email address is real, gating Login until complete. Tracked on an additive, nullable `users.email_verified_at` timestamp — deliberately **not** a `UserStatus` value (`UserStatus` stays exactly `ACTIVE`/`DISABLED`). See [`03-authentication-flow.md`](./03-authentication-flow.md) and [`adr/ADR-005-email-verified-at-column.md`](./adr/ADR-005-email-verified-at-column.md).

**Invitation** 🟡 *(Future Version — not built in V1)*
The mechanism by which a new team member joins an existing Organization, issued by an Owner (email + intended Role). Has its own lifecycle: `Pending → Accepted / Expired / Cancelled`. The User record is only created upon acceptance. See [`08-identity-lifecycle.md`](./08-identity-lifecycle.md) and [`adr/ADR-004-invitation-based-onboarding.md`](./adr/ADR-004-invitation-based-onboarding.md).

**Membership**
The architectural concept — deliberately not implemented in V1 — of a User's relationship to a specific Organization carrying its own independent Role, allowing (in a future version) one User to belong to multiple Organizations with different Roles in each. See the *Future Evolution: Multi-Organization Membership* note in [`08-identity-lifecycle.md`](./08-identity-lifecycle.md).

**Cross-Cutting Concern**
An architectural classification for functionality (like Authentication and Security) that sits outside and before the Business Domain, applying uniformly across it rather than being owned by any single business feature. See [`01-overview.md`](./01-overview.md).

**Defense in Depth**
The security principle that no single layer is ever solely responsible for protecting the system — every layer (HTTPS, Authentication, Authorization, Validation, Business Rules, Database Constraints) backs up the ones around it. See [`07-security.md`](./07-security.md).

**Least Privilege**
The security principle that every Identity is granted the minimum set of permissions necessary — never more. See [`07-security.md`](./07-security.md).

**Secure by Default**
The security principle that any new feature must be secure from the moment it is built, rather than relying on someone remembering to secure it later. See [`07-security.md`](./07-security.md).

**Audit Log**
The record of important security-relevant events (login, failed login, key creation/rotation/revocation, password change, role change, agent creation/archiving) kept so that any future incident can be traced. See [`07-security.md`](./07-security.md).
