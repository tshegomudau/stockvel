# Initial findings (baseline)

**Date:** 2026-11-10  
**Method:** Static analysis of Services/Controllers/Middleware/Views + confirmation against schema  
**Baseline tests:** `php bin/console test --env=testing` → **126 passed, 0 failed, 0 blocked**  
**CodeRabbit CLI:** installed `0.9.4`; `auth status --agent` → `credentials_unavailable` in this agent environment (sign-in required in a trusted terminal before PR reviews).

> Passing tests do **not** mean production readiness. Several high-severity gaps are untested or only partially covered.

## Confirmed findings

| ID | Severity | Module | Defect | Evidence |
|----|----------|--------|--------|----------|
| AUD-001 | **Critical** | Payments webhook | HTTP webhook accepts unsigned payloads and marks events `processed` without calling signature-verified `PaymentWebhookService` | `WebhookController::payment` stores signature but never verifies; route CSRF-exempt |
| AUD-002 | **Critical** | Payments webhook | `PaymentWebhookService` SQL columns (`event_id`, `payload`, `processing_status`, …) **do not match** migration `003` schema (`external_id`, `payload_json`, `status`) — service cannot run on current DB | Schema vs service mismatch |
| AUD-003 | **High** | Loans | Direct `/app/loans/{id}/approve` can self-approve; bypasses approvals inbox `assertNotRequester` | `LoanService::committeeApprove` has no applicant≠approver check |
| AUD-004 | **High** | Contributions | Single capture passes `null` idempotency key → double-submit duplicates money + journals | `ContributionController::store` |
| AUD-005 | **High** | Tenancy | `TenantMiddleware` allows `suspended` memberships with full role permissions | Status allowlist includes `suspended` |
| AUD-006 | **High** | Tenancy | `pending` invitees inherit full assigned role permissions before admin activation | Same middleware; comment claims “limited” access |
| AUD-007 | **High** | Funds | Debits/credits skip dual-approval; balance read without `FOR UPDATE` | `FundService::post` / `balance` |
| AUD-008 | **Medium** | Loans | Guarantor membership IDs not validated to current `stokvel_id` | `LoanService::apply` |
| AUD-009 | **Medium** | Loans | Repayments lack idempotency | `LoanService::repay` |
| AUD-010 | **Medium** | Money | `MoneyMath::format` always casts via `(float)` even when bcmath exists | `MoneyMath.php` |
| AUD-011 | **Medium** | Support | Same-stokvel users can open others’ tickets by ID | `SupportTicketController::show` |
| AUD-012 | **Medium** | Sessions | Session dir chmod `0777` | `Session::ensureDir` |
| AUD-013 | **Medium** | Mail | Log driver writes full bodies including reset tokens to `storage/mail/` | `MailChannel::writeLog` |
| AUD-014 | **Low** | Auth logs | Failed login logs raw email | `AuthService::login` |
| AUD-015 | **Low** | Members | Direct create discards generated temp password (ops lockout) | `MemberController` |

## Positive controls observed

- Most finance queries bind `stokvel_id`
- CSRF on mutating app/platform/member routes
- Argon2id passwords, MFA (TOTP/passkeys), session regenerate on login
- Approval inbox blocks self-approve; contribution edits are maker-checker
- Document path traversal checks; HtmlSanitizer + CSP
- Suspended **stokvels** redirected away from tenant UI
- Get-started path for users with no membership (avoids bare 403)

## Priority order for Phase 3

1. AUD-001 + AUD-002 (webhook signature + schema alignment)  
2. AUD-003 (loan self-approve)  
3. AUD-004 (contribution idempotency)  
4. AUD-005 + AUD-006 (membership status gating)  
5. AUD-007 / AUD-008 / AUD-009 / AUD-010 (funds, guarantors, repay idempotency, MoneyMath)
