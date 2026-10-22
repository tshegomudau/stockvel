# TEST_RESULTS

**Executed:** 2026-10-20  
**Test database:** `stockvel_testing` (confirmed via TestingGuard / suite banner)  
**Environment:** `.env.testing` (`APP_ENV=testing`, `MAIL_DRIVER=log`, `PAYMENT_DRIVER=mock`, `SMS_DRIVER=log`)  
**Destructive ops:** `db:reset` and `seed:e2e` ran only against `stockvel_testing` — development/production databases were not reset.  
**Stop rule:** Stop after 3 failed suite attempts — not triggered (both attempts green).

## Commands used

```bash
php bin/console db:reset --env=testing
php bin/console seed:e2e --env=testing
php bin/console test:all --env=testing
```

## Attempt log

| Attempt | Scope | Result | Notes |
|--------:|-------|--------|-------|
| 1 | Baseline suite after product upgrade | **50 passed, 0 failed** | Fresh reset + seed |
| 2 | Expanded suite (+ product-upgrade regression tests) + ReportController fix | **57 passed, 0 failed** | Fresh reset + seed |

Machine-readable: `storage/test-artifacts/last-run.json`  
Attempt logs: `storage/test-artifacts/regression-attempt-1.log`, `regression-attempt-2.log`

## Final suite (`php bin/console test:all --env=testing`)

| Metric | Count |
|--------|------:|
| Total tests executed | 57 |
| Passed | 57 |
| Failed | 0 |
| Blocked | 0 |

### New regression coverage (attempt 2)

- COA `bank` + `loan_receivable` ensure
- Chairperson upgrade routes (support, loan products, burial, grocery, docs create, CSV template, reports CSV export)
- Member portal (profile, change-requests, loan apply)
- Platform support / roles / users / audit
- Meeting show (attendance column)
- Encrypted ID in `member_change_requests` payload

### Role / tenant coverage executed

- Platform admin: `/platform`, jobs, support, roles, users, audit
- Stokvel A chairperson: core + upgrade modules
- Stokvel A treasurer: finance + CSV template
- Stokvel A secretary: governance + meeting show
- Stokvel A ordinary member: management denied; member portal upgrade routes
- Stokvel B chairperson: isolated dashboard/members/approvals

## Browser / Playwright

Not executed. No Playwright dependency installed. HTTP coverage used in-process Router dispatch with shared session.

## Release gate assessment

| Gate | Result |
|------|--------|
| PHP syntax | Pass |
| Migrations on clean test DB (10 files) | Pass |
| E2E seeders on test DB | Pass |
| Critical automated workflows | Pass (57/57) |
| Reports CSV export (ReportService) | Pass (fixed controller) |
| Tenant isolation / permissions | Pass |
| Full browser matrix / responsive / a11y | **Blocked** |
| Production DB exercise | **Not run** (safety: only `stockvel_testing`) |

**Verdict:** Automated critical path on `stockvel_testing` is green after fresh migrate/seed with expanded upgrade coverage. Interactive browser E2E remains blocked (no Playwright).

### Fake credentials (test only)

Password for all seeded users: `Password123!`  
Examples: `admin@stockvel.local`, `thabo.mokoena@example.co.za`, `nomsa.dlamini@example.co.za`, `themba.zulu@example.co.za`
