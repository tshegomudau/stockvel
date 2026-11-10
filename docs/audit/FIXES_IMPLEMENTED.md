# Fixes implemented

**Branch:** `audit/production-2026-11-10`

| Change | Files |
|--------|-------|
| Webhook signature verification + schema-aligned persistence | `WebhookController.php`, `PaymentWebhookService.php`, `MockPaymentGateway.php` |
| Loan self-approve block + tenant-scoped guarantors | `LoanService.php`, `LoanLifecycleTest.php` |
| Contribution capture idempotency token | `ContributionController.php`, `contributions/create.php` |
| Suspended/pending membership gating | `TenantMiddleware.php` |
| Fund post row lock + MoneyMath bc format | `FundService.php`, `MoneyMath.php` |
| In-process raw body for webhook HTTP tests | `Request.php` |
| Support ticket peer isolation | `SupportTicketController.php`, `SupportTicketTest.php` |
| Session directory permissions 0770 | `Session.php` |
| Regression suite | `tests/AuditSecurityFixesTest.php` |
| Discovery docs | `docs/audit/*` |

No production database mutations. No destructive migrations. No secrets committed.
