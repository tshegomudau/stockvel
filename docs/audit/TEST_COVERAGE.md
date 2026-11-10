# Test coverage inventory

**Runner:** custom `Tests\TestRunner` via `php bin/console test --env=testing`  
**Baseline (2026-11-10):** 126 passed, 0 failed, 0 blocked  
**Not PHPUnit/Pest.** DB: `stockvel_testing` (isolated).

## Suite map

| File | Focus | Approx. cases |
|------|-------|---------------|
| `UnitTest.php` | MoneyMath, loan schedule, syntax, password policy, timezone | 5 |
| `AuthTest.php` | Register/login/reset, suspended stokvel, claim→chairperson | 6 |
| `TotpMfaTest.php` | TOTP + MFA gate | 4 |
| `SecurityTest.php` | CSRF, validator, SQLi attempt | 4 |
| `HtmlSanitizerTest.php` | XSS sanitizer | 2 |
| `TenantTest.php` | Cross-tenant UPDATE blocked | 2 |
| `FinanceTest.php` | Charges, payments, journals, maker-checker, reports | 10 |
| `E2EWorkflowTest.php` | Dual membership, isolation, contributions, permissions | 8 |
| `HttpRouteTest.php` | Public/auth/protected smoke | 9 |
| `RoleMatrixTest.php` | Role HTTP matrix | 7 |
| `ApprovalMultiApproverTest.php` | Multi-approver + contribution edit | 4 |
| `LoanLifecycleTest.php` | Apply→approve→disburse→repay | 1 |
| `Fine*` / `AutoFine*` | Fines + auto fine | 7 |
| `FundReconciliationTest.php` | Fund balances | 2 |
| `Meeting` / `Rules` | Governance workflows | 5 |
| `CsvImportExportTest.php` | CSV I/O | 4 |
| `CategoryModulesTest.php` | Categories / beneficiaries / purposes | 6 |
| `SupportTicketTest.php` | Tickets + cross-tenant | 5 |
| `ProductUpgradeRegressionTest.php` | Upgrade routes, encrypted ID | 7 |
| `SavingGoalProfileHealthTest.php` | Goals / health / profile | 3 |
| `StatementBalanceTest.php` | Statement reconcile | 1 |
| `MailChannelTest.php` | Log driver | 2 |
| `UiLayoutTest.php` | Forms / responsive CSS | 9 |
| `AuditLogAccessTest.php` | Platform audit + app logs RBAC | 6 |
| `CronTest.php` | Cron locks | 3 |
| `IntegrationTest.php` | Audit, docs path traversal | 4 |

## Gaps (relative to audit scenarios)

| Scenario | Coverage today |
|----------|----------------|
| A — Guest stokvel create → approve → claim | Partial (UI smoke + claim membership test); email notify not fully asserted |
| B — Invitation accept → activate | Partial |
| C — Contribution lifecycle | Strong for service path; **missing** double-submit idempotency |
| D — Platform admin RBAC | Partial (audit, roles load); not every platform form |
| E — Tenant isolation | Present for several paths; not exhaustive IDOR matrix |
| F — Failure/recovery | Cron locks + some idempotency; **webhook unsigned**, loan self-approve untested |
| Payment webhook signature | **Missing** |
| Suspended/pending membership gate | **Missing / contradicting product intent** |
| Live payment gateways | N/A (mock only) |

## Quality tooling

| Tool | Status |
|------|--------|
| Custom test suite | Used |
| PHPStan / Psalm | Not configured |
| Composer audit | Available via `composer audit` (run in Phase 2) |
| ESLint / frontend build | N/A (no SPA build) |
| CI pipeline | None |
| CodeRabbit | CLI present; agent auth blocked until interactive login |
