# Remaining issues

Prioritized for follow-on work. Do not treat as approved exceptions without product sign-off.

| ID | Severity | Issue | Suggested safe approach |
|----|----------|-------|-------------------------|
| AUD-007b | High | Fund debit/credit skip dual-approval / expense workflow | Route material debits through `ApprovalService` when `require_dual_approval` |
| AUD-009 | Medium | Loan repayments non-idempotent | Require idempotency key on repay UI + service |
| AUD-011 | Medium | Same-stokvel users can read peers’ support tickets | **Fixed** — creator or `support.manage` only |
| AUD-012 | Medium | Session directory chmod 0777 | **Fixed** — mkdir/chmod 0770 |
| AUD-013 | Medium | Mail log driver stores reset/invite URLs | Redact tokens in log driver; rotate `storage/mail` permissions |
| AUD-014 | Low | Failed login logs raw email | Hash/truncate email in security logs |
| AUD-015 | Low | Direct member create discards temp password | Email invite / one-time set-password link |
| AUD-016 | Low | `RateLimitMiddleware` unused on routes | Attach to login/register/forgot-password |
| AUD-017 | Medium | No CI pipeline | Add GitHub Action running `test --env=testing` on PR |
| AUD-018 | — | CodeRabbit not authenticated in agent env | Operator: `coderabbit auth login --agent` then review vs `main` |
| AUD-019 | — | Platform admin UI exhaustive click-through | Continue Phase 4 with browser checks |
| AUD-020 | — | Email template XSS/injection matrix | Expand beyond HtmlSanitizer unit tests |

**Stopped-for-approval items:** none pending destructive schema drops. Additive migration for webhook columns was intentionally avoided by aligning code to existing schema.
