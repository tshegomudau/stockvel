# End-to-end test plan

**Test database:** `stockvel_testing` only  
**Base URL:** `http://127.0.0.1:8090`  
**Seed password (all fixtures):** `Password123!`  
**Commands:**

```bash
php bin/console db:reset --env=testing
php bin/console seed:e2e --env=testing
php bin/console test:all --env=testing
```

Destructive reset/seed is refused unless the configured database name clearly identifies as a test database.

## Seeded accounts

| Role | Email | Tenant |
|------|-------|--------|
| Platform Super Admin | admin@stockvel.local | Platform |
| Platform Support | support@stockvel.local | Platform |
| Platform Auditor | auditor@stockvel.local | Platform |
| Chairperson | thabo.mokoena@example.co.za | Ubuntu (A) |
| Treasurer | nomsa.dlamini@example.co.za | Ubuntu (A) |
| Secretary | sipho.ndlovu@example.co.za | Ubuntu (A) |
| Committee | committee.ubuntu@example.co.za | Ubuntu (A) |
| Ordinary | lerato.pillay@example.co.za | Ubuntu (A) |
| Stokvel Auditor | stokvel.auditor.ubuntu@example.co.za | Ubuntu (A) |
| Suspended | suspended.ubuntu@example.co.za | Ubuntu (A) |
| Dual member | dual.member@example.co.za | Ubuntu + Masakhane |
| Unverified | unverified@example.co.za | none |
| Locked | locked@example.co.za | none |
| Custom perms | custom.perms@example.co.za | Ubuntu (A) |
| No reports | noreports@example.co.za | Ubuntu (A) |
| Chairperson B | themba.zulu@example.co.za | Masakhane (B) |
| Treasurer B | patience.molefe@example.co.za | Masakhane (B) |
| Valid invite token | invite.valid@example.co.za | hash of `valid-invite-token-e2e` |
| Expired invite | invite.expired@example.co.za | hash of `expired-invite-token-e2e` |

---

## Executed automated cases

| Test ID | Module | User role | Tenant | Preconditions | Test data | Steps | Expected result | Actual result | Status | Error found | Root cause | Fix implemented | Retest result |
|---------|--------|-----------|--------|---------------|-----------|-------|-----------------|---------------|--------|-------------|------------|-----------------|---------------|
| AUTH-01 | Auth | Guest | — | Clean DB seed | new email | Register + login via AuthService | User created and authenticated | Pass | Pass | — | — | Existing flow | Pass |
| AUTH-02 | Auth | Guest | — | Seeded user | reset token | Password reset consume | Login with new password | Pass | Pass | — | — | — | Pass |
| SEC-01 | Security | Guest | — | Session | invalid CSRF | Csrf::validate | CsrfException | Pass | Pass | — | — | — | Pass |
| SEC-02 | Security | — | — | — | bad email | Parameterised query | No row | Pass | Pass | — | — | — | Pass |
| TEN-01 | Tenancy | — | A/B | Seeded charges | wrong stokvel_id | UPDATE with wrong tenant | 0 rows | Pass | Pass | — | — | — | Pass |
| TEN-02 | Tenancy | Dual | A/B | dual.member | switch stokvel | Login once, switch A→B | Context + perms change | Pass | Pass | Stale session after rolled-back auth | Session kept deleted user id | Clear session after test_transaction; harden audit FK | Pass |
| TEN-03 | Tenancy | — | A vs B | Seeded members | forged stokvel_id | SELECT membership | null | Pass | Pass | — | — | — | Pass |
| FIN-01 | Contributions | Treasurer | A | Seed + COA | R25 cash | capturePayment | Posted balanced journal | Pass | Pass | Services used missing tables | Schema/service mismatch | ContributionService→payment_transactions; LedgerService aligned; operational post flag | Pass |
| FIN-02 | Finance | — | A | Seed | charges | Count member_charges | > 0 | Pass | Pass | — | — | — | Pass |
| FIN-03 | Finance | — | A | Seed | idempotency key | Duplicate insert | Constraint failure | Pass | Pass | — | — | — | Pass |
| FIN-04 | Finance | — | A | Seed | maker-checker | Self-approve journal | Denied | Pass | Pass | — | — | — | Pass |
| PERM-01 | Permissions | Chair | A | Seed | settings.manage | can() | Allowed (* ) | Pass | Pass | Controller codes ≠ role map | PermissionService rewritten to dotted codes + legacy map | Pass |
| PERM-02 | Permissions | Ordinary | A | noreports | reports.view | can() | Denied | Pass | Pass | ordinary_member had view_financial_reports | Removed from DemoSeeder role map | Pass |
| PERM-03 | Permissions | Platform support | — | — | contributions.capture | forPlatformRole | Denied | Pass | Pass | Platform roles got `*` tenant perms | TenantContext::can no longer auto-allows platform; support read-only set | Pass |
| HTTP-01 | Public | Guest | — | — | / /health | GET | 200 | Pass | Pass | — | — | — | Pass |
| HTTP-02 | Auth | Guest | — | — | /app | GET | 401/302/403 | Pass | Pass | — | — | — | Pass |
| HTTP-03 | Contributions | Treasurer | A | Login+switch | /app/contributions | GET | 200 | Pass | Pass | Curl hit live server without session | HttpClient defaults to in-process | Pass |
| HTTP-04 | Fees | Ordinary | A | Login | /app/fees | GET | 403/302 | Pass | Pass | — | — | — | Pass |
| HTTP-05 | Platform | Super Admin | — | Login | /platform/jobs | GET | 200 | Pass | Pass | Query on failed_jobs | JobController→notification_jobs | Pass |
| HTTP-06 | CSRF | Guest | — | — | POST /login no token | POST | Not silent 200 success | Pass | Pass | — | — | — | Pass |
| CRON-01 | Jobs | — | — | — | processDue ×2 | Run twice | Idempotent | Pass | Pass | — | — | — | Pass |
| UNIT-01 | Money | — | — | — | 0.1+0.2 | MoneyMath::add | Decimal-safe | Pass | Pass | — | — | — | Pass |
| UNIT-02 | Loans | — | — | — | flat schedule | buildSchedule | 5 rows, principal sums | Pass | Pass | — | — | — | Pass |

---

## Inventory coverage (not yet fully browser-executed)

The following areas from the mandatory inventory remain **blocked / not fully executed** as interactive browser E2E (Playwright not installed). Automated service/HTTP coverage exists only where listed above.

| Area | Status |
|------|--------|
| Full registration UI + email verification browser flow | Blocked — no Playwright run |
| Onboarding wizard all 15 steps in browser | Blocked |
| Bulk CSV import UI | Partial (service/controller paths only) |
| Expense approval + receipt upload browser | Partial (ExpenseController wired to ExpenseService) |
| Loan product→disbursement→repayment browser | Partial (LoanService schema-aligned; no browser) |
| Payout cycle UI | Partial |
| Meetings/minutes/voting browser | Blocked |
| Document MIME/path-traversal download browser | Partial (secure path unit/integration) |
| All report types + CSV formula injection browser | Partial (ReportService filter test) |
| Responsive 360–1440 screenshots | Blocked |
| Accessibility audit | Blocked |

Do **not** treat blocked rows as passed.
