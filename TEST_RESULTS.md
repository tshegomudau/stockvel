# TEST_RESULTS

**Executed:** 2026-10-16  
**Test database:** `stockvel_testing` (confirmed via TestingGuard / suite banner)  
**Environment:** `.env.testing` (`APP_ENV=testing`, `MAIL_DRIVER=log`, `PAYMENT_DRIVER=mock`, `SMS_DRIVER=log`)  
**Destructive ops:** `db:reset` and `seed:e2e` ran only against `stockvel_testing` — development/production databases were not reset.

## Commands used

```bash
php bin/console db:reset --env=testing
php bin/console seed:e2e --env=testing
php bin/console test:all --env=testing
```

## Final suite (`php bin/console test:all --env=testing`)

| Metric | Count |
|--------|------:|
| Total tests executed | 50 |
| Passed | 50 |
| Failed | 0 |
| Blocked | 0 |

Machine-readable last run: `storage/test-artifacts/last-run.json`

### Role / tenant coverage executed

- Platform admin: `/platform`, `/platform/jobs`
- Stokvel A chairperson: dashboard + members/fees/contributions/expenses/approvals/loans/payouts/meetings/rules/documents/reports/notifications/settings
- Stokvel A treasurer: finance + approvals + reports
- Stokvel A secretary: members/meetings/rules/documents
- Stokvel A ordinary member: management routes denied; `/member` accessible
- Stokvel B chairperson: isolated dashboard/members/approvals

## Modules tested (automated)

- Auth, security, tenancy, permissions
- Contributions + ledger integrity
- Approvals (`approval_requests`)
- Documents (status-based listing)
- Payouts, rules, loans schedule, reports
- Cron / notifications / platform jobs
- Role matrix HTTP crawl (A + B + platform)

## Browser / Playwright

Not executed. No Playwright dependency installed. HTTP coverage used in-process Router dispatch with shared session.

## Release gate assessment

| Gate | Result |
|------|--------|
| PHP syntax | Pass |
| Migrations on clean test DB (8 files) | Pass |
| E2E seeders on test DB | Pass |
| Critical automated workflows | Pass (50/50) |
| Approvals SQL error | Fixed |
| Tenant isolation tests in suite | Pass |
| Permission tests in suite | Pass |
| Financial journal balance on capture | Pass |
| Full browser matrix / responsive / a11y | **Blocked** |
| Production DB exercise | **Not run** (safety: only `stockvel_testing`) |

**Verdict:** Automated critical path on `stockvel_testing` is green after fresh migrate/seed. The application is **not** production-ready until blocked browser workflows and remaining inventory items in `docs/END_TO_END_TEST_PLAN.md` are executed.

### Fake credentials (test only)

Password for all seeded users: `Password123!`  
Examples: `admin@stockvel.local`, `thabo.mokoena@example.co.za`, `nomsa.dlamini@example.co.za`, `themba.zulu@example.co.za`
