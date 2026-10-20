# stockvel Implementation Status

Multi-tenant Stokvel Management Web Application (vanilla PHP 8.3+, MySQL 8+, vanilla JS).

Legend: `[ ]` pending · `[~]` partial · `[x]` complete · `[!]` blocked (external)

---

## 1. Core architecture

- [x] Directory structure (MVC-style, framework-free)
- [x] Front controller (`public/index.php`)
- [x] Router (GET, POST, PUT/PATCH simulation, DELETE)
- [x] Dependency container / service registry
- [x] Environment loader (`.env` + `.env.testing`)
- [x] Central exception handler
- [x] Structured logger
- [x] Flash messages / CSRF / sessions
- [x] Pagination + validation
- [x] View renderer with layouts and partials
- [x] Middleware pipeline
- [x] Database migrator (`001`–`008`) + seeders
- [x] CLI (`bin/console`) including `setup`, `migrate:status`, test suites
- [x] TestingGuard (refuse destructive ops on non-test DB names)

## 2–25. Product domains

Implemented domains from the product specification:

- Multi-tenancy with `stokvel_id` enforcement and switcher
- Auth (register, login, logout, reset, verify, lockout, remember-me, Argon2id)
- RBAC (platform + stokvel roles/permissions)
- Onboarding wizard with resume (`stokvel_onboarding`)
- Membership, invitations, status history
- Fee structures with versioning
- Double-entry style ledger services
- Contributions, allocations, idempotency, mock payment gateway
- Expenses, loans, rotational payouts (`payout_cycles`)
- Meetings, rules/constitution versioning
- Approvals (`approval_requests`)
- Secure documents outside public web root
- Notifications with queue/retry + log mail driver
- Platform / stokvel / member dashboards
- Reports + CSV export
- Custom CSS/JS UI (no CDN frameworks)
- Audit trail, cron jobs with locks
- Lightweight test runner (no PHPUnit dependency)

---

## Verification results (2026-10-20)

| Check | Status | Notes |
|-------|--------|-------|
| Migrations | **pass** | 8 files (`001`–`008`) on clean `stockvel_testing` |
| Seeders | **pass** | `DemoSeeder` + `E2ETestSeeder` (idempotent demo re-run) |
| Tests | **pass** | **50 passed, 0 failed, 0 blocked** (`test:all`) |
| Role matrix HTTP | **pass** | Platform, Ubuntu roles, Masakhane chair |
| Tenant isolation | **pass** | Included in suite |
| Security tests | **pass** | CSRF, SQLi, IDOR covered |
| Browser / Playwright | **blocked** | Not installed; see `docs/END_TO_END_TEST_PLAN.md` |

### Demo credentials (development)

| Role | Email | Password |
|------|-------|----------|
| Platform super admin | `admin@stockvel.local` | `Password123!` |
| Platform support | `support@stockvel.local` | `Password123!` |
| Ubuntu chairperson | `thabo.mokoena@example.co.za` | `Password123!` |
| Ubuntu treasurer | `nomsa.dlamini@example.co.za` | `Password123!` |

### New developer commands

```bash
cp .env.example .env   # set DB_*
php bin/console setup
php bin/console serve 127.0.0.1 8090
```

### External credentials still required for production

SMTP, SMS provider, and real payment gateway keys — local uses `log` / `mock` drivers.
