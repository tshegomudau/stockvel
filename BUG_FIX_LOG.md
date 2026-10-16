# BUG_FIX_LOG

## BF-001 — Missing `failed_jobs` table on platform jobs

- **Error:** `SQLSTATE[42S02] Table 'stokvel.failed_jobs' doesn't exist`
- **Route / action:** `GET /platform/jobs`
- **Reproduction:** Login as platform admin → open Failed jobs
- **Root cause:** JobController queried a Laravel-style table never migrated
- **Files changed:** `app/Controllers/Platform/JobController.php`, `app/Views/platform/jobs/index.php`, `app/Controllers/Platform/DashboardController.php`
- **Fix:** Query `notification_jobs` where `status IN ('failed','dead')`
- **Regression test:** `Platform jobs page uses notification_jobs` (HTTP)
- **Final status:** Fixed / passing

## BF-002 — Permission codes mismatched controllers

- **Error:** Widespread HTTP 403 on module routes for legitimate officers
- **Route / action:** `/app/fees`, `/app/contributions`, `/app/members`, etc.
- **Reproduction:** Login as treasurer/secretary → open module
- **Root cause:** Controllers required dotted codes (`fees.manage`) while PermissionService / seeder used different vocabularies; platform roles received blanket `*`
- **Files changed:** `app/Services/PermissionService.php`, `app/Core/TenantContext.php`, `app/Services/Auth/AuthService.php`, `database/seeders/DemoSeeder.php`
- **Fix:** Align role→permission map to controller codes; map legacy seeder codes; platform support/auditor get read-only tenant perms; remove ordinary_member financial report grant
- **Regression test:** Permission matrix + ordinary fees denial + noreports + platform support capture denial
- **Final status:** Fixed / passing

## BF-003 — Finance services SQL vs migrated schema

- **Error:** Missing tables/columns (`contribution_payments`, journal `uuid`/`reference`, expense `category_id`, etc.)
- **Route / action:** Contribution capture, expense create, ledger post
- **Reproduction:** `ContributionService::capturePayment` on migrated DB
- **Root cause:** Service layer written against a different schema than migrations 001–007
- **Files changed:** `app/Services/Finance/ContributionService.php`, `ExpenseService.php`, `FeeStructureService.php`, `LedgerService.php`, `LoanService.php`, `PayoutService.php`, controllers for contributions/fees/expenses
- **Fix:** Use `payment_transactions` / `payment_allocations`; ledger columns `entry_number` / `created_by_user_id` / `posted_by_user_id`; operational `post(..., true)` for system postings; expense/fee controllers call real services
- **Regression test:** `Contribution capture posts balanced journal`, partial payment allocation
- **Final status:** Fixed / passing

## BF-004 — CLI tests crashed on `setcookie` / `header`

- **Error:** `Cannot modify header information - headers already sent`
- **Route / action:** Auth login/logout during PHPUnit-less CLI suite
- **Reproduction:** `php bin/console test:all` after suite echo started
- **Root cause:** Remember-me cookies and security headers sent in CLI after output
- **Files changed:** `RememberTokenService.php`, `SecurityHeadersMiddleware.php`, `Session.php`
- **Fix:** Skip cookie/header emission when `headers_sent()` or `PHP_SAPI === 'cli'`
- **Regression test:** Full suite auth/e2e cases
- **Final status:** Fixed / passing

## BF-005 — HTTP client used live server without session

- **Error:** Authenticated route returned 401 after successful AuthService::login
- **Route / action:** `GET /app/contributions` in HttpRouteTest
- **Reproduction:** Login in-process then HttpClient GET while something listened on :8090
- **Root cause:** HttpClient preferred curl when server reachable, losing PHP session
- **Files changed:** `tests/Support/HttpClient.php`
- **Fix:** Default in-process dispatch; curl only if `STOKVEL_HTTP_CLIENT=curl`
- **Regression test:** Login with seeded treasurer reaches contributions
- **Final status:** Fixed / passing

## BF-006 — Stale session user after rolled-back auth tests

- **Error:** `FK fk_audit_logs_user` on login/logout audit insert
- **Route / action:** Subsequent E2E logins after AuthTest transaction
- **Reproduction:** Run AuthTest (transaction rollback) then dual-member login
- **Root cause:** Session/tenant still held deleted user id; audit inserted that user_id
- **Files changed:** `tests/bootstrap.php` (`test_transaction`), `AuditService.php`, `AuditRepository.php`
- **Fix:** Clear session/tenant after rollback; null out non-existent audit user_ids
- **Regression test:** Dual membership tenant switch + chairperson permission cases
- **Final status:** Fixed / passing

## BF-007 — Incomplete chart of accounts for ledger posting

- **Error:** Missing `bank` / `contributions` sub_type accounts after DemoSeeder
- **Route / action:** Contribution capture ledger post
- **Reproduction:** Capture payment on DemoSeeder-only COA (3 accounts, no sub_type)
- **Root cause:** DemoSeeder inserted minimal COA without sub_types required by ChartOfAccountsService
- **Files changed:** `database/seeders/E2ETestSeeder.php` (`ensureFullChartOfAccounts`)
- **Fix:** Upsert full default chart with sub_types after demo seed
- **Regression test:** Contribution capture posts balanced journal
- **Final status:** Fixed / passing

## BF-008 — Unsafe test bootstrap could hit development DB

- **Error:** (prevented) Tests previously migrated/seeded whatever `DB_DATABASE` was
- **Route / action:** `php bin/console test`
- **Reproduction:** Run tests with `.env` pointing at `stokvel`
- **Root cause:** No env isolation / name guard
- **Files changed:** `.env.testing`, `bootstrap/app.php`, `bin/console`, `app/Core/TestingGuard.php`, `tests/bootstrap.php`, `config/database.php` (existing TEST_DB_*)
- **Fix:** Force testing env for test commands; refuse ops unless DB name matches test pattern; confirm `SELECT DATABASE()`
- **Regression test:** Suite prints `Using test database: stockvel_testing`
- **Final status:** Fixed / passing

## BF-009 — Payouts / rules / loans schema leftovers

- **Error:** Controllers and services referenced non-migrated tables/columns (`stokvel_rules`, rotation-cycle assumptions, incomplete loan product / report joins)
- **Route / action:** `GET /app/payouts`, `GET /app/rules`, loan product create, report cashflow/payout/expense paths
- **Reproduction:** Open payouts or rules as chairperson/secretary after migrate on clean schema
- **Root cause:** Follow-up modules still partially written against pre-migration names after BF-003 finance alignment
- **Files changed:** `PayoutController.php`, `PayoutService.php`, `RuleController.php`, `RuleService.php`, `LoanController.php`, `LoanProductService.php`, `MeetingController.php`, `ReportController.php`, `CsvImportService.php`, create views for payouts/loans/meetings
- **Fix:** Use `payout_cycles` / `rules` / `loan_products` columns from migrations; register RuleService; clean swap/order and report queries; CSV import idempotency keys
- **Regression test:** `Payouts index uses payout_cycles`, `Rules index uses rules table` (HTTP)
- **Final status:** Fixed / passing

## BF-010 — Missing `approvals` table

- **Error:** `SQLSTATE[42S02] Table 'stockvel_testing.approvals' doesn't exist`
- **Route / action:** `GET /app/approvals`, dashboard pending count (already on `approval_requests`)
- **Reproduction:** `db:reset` + `seed:e2e` then open Approvals as treasurer/chairperson
- **Root cause:** Controller queried invented `approvals` table; migrations define `approval_requests`
- **Files changed:** `ApprovalController.php`, `approvals/index.php`, `E2ETestSeeder.php`
- **Fix:** Query/update `approval_requests` with `approved_by_user_id` / `rejected_by_user_id`; seed pending row
- **Regression test:** `Approvals page uses approval_requests`, chairperson/treasurer role matrix
- **Final status:** Fixed / passing

## BF-011 — Broader schema mismatches blocking role workflows

- **Error:** Missing `member_invitations`, `member_ledger_entries`, `onboarding_progress`, `payment_webhooks`, `members`, documents `deleted_at`/`visibility`
- **Route / action:** invitations, member statement/dashboard, onboarding, webhooks, CSV import, `/app/documents`
- **Reproduction:** Role-matrix HTTP crawl after fresh migrate
- **Root cause:** Controllers still used pre-migration table/column names
- **Files changed:** Invitation/Member/Onboarding/Auth/Profile/Webhook/Document controllers, MembershipService/Repository, CsvImportService, migration `008_create_approvals_reconcile_ack.php`
- **Fix:** Align to `invitations`, `payment_transactions`/`member_charges`, `stokvel_onboarding`, `webhook_events`, `memberships`, documents `status` + DocumentService; add meeting acknowledgement + bank reconciliation tables
- **Regression test:** `Chairperson core modules load without SQL errors` (+ treasurer/secretary/platform/B matrix)
- **Final status:** Fixed / passing
