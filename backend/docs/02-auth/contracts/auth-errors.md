# Contract: Authentication & Authorization Errors

> This is an implementation-ready specification, derived directly from the "Fail Securely" and "Don't Expose Internal Details" principles in [`07-security.md`](../07-security.md).

---

## 1. Governing Principle

> **Never expose internal system details in an error response. Internal details go to Logs; the client only ever sees a generic, safe message.**

This applies identically whether the failure came from a wrong password, an expired JWT, a revoked API Key, or a missing database row — the client-facing message must not reveal which one it was.

---

## 2. Authentication Failures

All authentication failures — regardless of the underlying, specific cause — return the same generic shape and status code.

| Underlying Cause (internal, logged only) | Client-Facing Response |
|--------------------------------------------|----------------------------|
| Wrong password | `401 Unauthorized` — `"Authentication failed."` |
| Unknown email | `401 Unauthorized` — `"Authentication failed."` |
| Expired JWT | `401 Unauthorized` — `"Authentication failed."` |
| Invalid JWT signature | `401 Unauthorized` — `"Authentication failed."` |
| Malformed / missing API Key | `401 Unauthorized` — `"Authentication failed."` |
| Revoked API Key | `401 Unauthorized` — `"Authentication failed."` |
| Archived Agent (`agents.status = ARCHIVED`) | `401 Unauthorized` — `"Authentication failed."` |
| Disabled User (`users.status = DISABLED`) | `401 Unauthorized` — `"Authentication failed."` |
| Unverified email (`users.email_verified_at IS NULL`) | `401 Unauthorized` — `"Authentication failed."` — tracked independently of `status`, see [`adr/ADR-005-email-verified-at-column.md`](../adr/ADR-005-email-verified-at-column.md). Not a `DISABLED` cause. |

### Example Response Body

```json
{
  "error": "authentication_failed",
  "message": "Authentication failed."
}
```

**Never** return responses like `"API Key abc123 does not exist"` or `"JWT signature verification failed"` — these leak internal implementation detail that helps an attacker (see [`07-security.md`](../07-security.md#9-principle-8--dont-expose-internal-details)).

---

## 3. Authorization Failures

Authorization failures are distinct from Authentication failures — the identity **was** verified, but the action is not permitted.

| Scenario | Client-Facing Response |
|----------|----------------------------|
| Authenticated Human lacks the required permission | `403 Forbidden` — `"You do not have permission to perform this action."` |
| Authenticated Agent attempts an action outside its Capability | `403 Forbidden` — `"You do not have permission to perform this action."` |

### Example Response Body

```json
{
  "error": "forbidden",
  "message": "You do not have permission to perform this action."
}
```

Per [`06-authorization.md`](../06-authorization.md#11-is-authorization-responsible-for-error-messages), Authorization itself only produces an `Allow` / `Deny` decision — the API layer is what translates a `Deny` into this HTTP response.

---

## 4. Invitation-Specific Errors 🟡 (Future Version — Not Applicable in V1)

Derived from [`08-identity-lifecycle.md`](../08-identity-lifecycle.md) and [`adr/ADR-004-invitation-based-onboarding.md`](../adr/ADR-004-invitation-based-onboarding.md). These error responses only become relevant once Team Management / Invitations ship; there is no invitation flow to trigger them in V1.

| Scenario | Client-Facing Response |
|----------|----------------------------|
| Registering with an email that has a pending invitation | `409 Conflict` — `"You already have a pending invitation."` |
| Accepting an expired invitation | `410 Gone` — `"This invitation has expired."` |
| Accepting a cancelled invitation | `410 Gone` — `"This invitation is no longer valid."` |
| Accepting an invitation for an email that already has an active account | `409 Conflict` — `"An account with this email already exists."` |

---

## 5. Ownership Constraint Errors

Derived from [`08-identity-lifecycle.md`](../08-identity-lifecycle.md#16-can-the-owner-remove-themselves).

| Scenario | Client-Facing Response |
|----------|----------------------------|
| Attempting to remove the last remaining Owner of an Organization | `409 Conflict` — `"An organization must always have at least one Owner."` |

---

## 6. HTTP Status Code Summary

| Status | Meaning in This System |
|--------|---------------------------|
| `401 Unauthorized` | Authentication failed — identity could not be verified |
| `403 Forbidden` | Authentication succeeded, but the action is not permitted |
| `409 Conflict` | The request conflicts with existing state (duplicate invitation, last owner, etc.) |
| `410 Gone` | The referenced resource (an invitation) is no longer valid |

---

## 7. Logging Requirement

Every authentication or authorization failure, while returning a generic message to the client, **must** be logged internally with full detail (see the Audit principle in [`07-security.md`](../07-security.md#6-principle-5--audit-everything-important)), including at minimum: timestamp, the specific failure reason, the identity or credential fragment involved (never the full secret), and the source IP/request context.
