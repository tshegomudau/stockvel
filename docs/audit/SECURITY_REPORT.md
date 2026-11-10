# Security report

**Date:** 2026-11-10  
**Scope:** Authn/authz, tenancy, finance integrity, webhooks, XSS/CSRF/SQLi posture  
**Baseline tools:** Custom test suite (131 pass), `composer audit` (no advisories)

## Critical / High addressed this branch

1. **Unsigned payment webhooks** — now signature-verified; invalid → 401; duplicates idempotent.
2. **Loan self-approval** — committee approve blocked for applicant user.
3. **Contribution double-post** — manual capture carries idempotency token.
4. **Suspended membership access** — TenantMiddleware denies.
5. **Pending membership privilege** — `/app` redirected; elevated permission codes stripped.
6. **Fund balance race** — row lock before balance mutation.

## Residual risk (not fully closed)

| Area | Risk | Notes |
|------|------|-------|
| Fund dual-control | High→Medium | Debits still immediate with `funds.manage`; maker-checker not wired |
| Loan repay idempotency | Medium | Replay can double-post until settled |
| Support ticket show | Medium | Same-tenant peer can open by ID |
| Session dir 0777 | Medium | Shared-host exposure |
| Mail log driver | Medium | Reset tokens on disk when `MAIL_DRIVER=log` |
| RateLimitMiddleware | Low | Not attached to routes (login lockout still exists) |
| Live payment gateways | N/A | Mock only — do not enable real money without new integration review |

## Controls that remain solid

- CSRF on mutating app/member/platform forms
- Tenant `stokvel_id` scoping on primary finance paths
- Argon2id, MFA, session regenerate
- HtmlSanitizer + CSP
- Parameterized SQL for request values
- `composer audit` clean for locked deps

## CodeRabbit

CLI `0.9.4` present. Agent environment returns `credentials_unavailable`. Re-run `coderabbit auth login --agent` in a trusted local terminal, then `coderabbit review --base main` on this branch before merge.
