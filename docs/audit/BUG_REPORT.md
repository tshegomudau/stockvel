# Bug report

**Branch:** `audit/production-2026-11-10`  
**Updated:** 2026-11-10

| ID | Severity | Module | Root cause | Reproduction | Fix | Tests | Status |
|----|----------|--------|------------|--------------|-----|-------|--------|
| AUD-001 | Critical | Payment webhook | Controller ignored HMAC and marked events processed | `POST /webhooks/payments` without signature → was 200 | Wire to `PaymentWebhookService`; 401 on bad signature | `AuditSecurityFixesTest` | **Fixed** |
| AUD-002 | Critical | Payment webhook | Service SQL columns mismatched migration 003 | Calling service would SQL-error on prod schema | Align inserts/selects to `external_id` / `payload_json` / `status` | Same | **Fixed** |
| AUD-003 | High | Loans | `committeeApprove` allowed applicant user_id | Applicant with `loans.manage` posts approve | Block self-approve | `LoanLifecycleTest` | **Fixed** |
| AUD-004 | High | Contributions | `store` passed null idempotency key | Double-submit create form | Form token + pass key to `capturePayment` | `AuditSecurityFixesTest` | **Fixed** |
| AUD-005 | High | Tenancy | Suspended memberships allowed in middleware | Suspend member → still `/app` | Deny suspended | `AuditSecurityFixesTest` | **Fixed** |
| AUD-006 | High | Tenancy | Pending invitees inherited full role perms | Accept treasurer invite → use `/app` before activate | Redirect `/app`→`/member`; strip elevated codes | Middleware change + suite green | **Fixed** |
| AUD-007 | High | Funds | Concurrent debit race | Parallel debits near zero | `SELECT … FOR UPDATE` on fund row | Existing fund tests + suite | **Fixed** (dual-approval still open — see REMAINING) |
| AUD-008 | Medium | Loans | Guarantors not tenant-validated | Cross-stokvel guarantor id | Require active same-stokvel membership | Suite + apply validation | **Fixed** |
| AUD-010 | Medium | MoneyMath | `format()` always used `(float)` | Large/edge decimal strings | Prefer `bcadd` when available | `UnitTest` | **Fixed** |
| AUD-011 | Medium | Support | Same-stokvel peers could open tickets by ID | Peer GET `/app/support/{id}` | Creator or `support.manage` only + tenant filter | `SupportTicketTest` | **Fixed** |
| AUD-012 | Medium | Sessions | Session dir chmod 0777 | Inspect `storage/sessions` mode | mkdir/chmod 0770 | Manual / deploy notes | **Fixed** |

Open items are listed in `REMAINING_ISSUES.md`.
