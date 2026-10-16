# stockvel Implementation Status

Multi-tenant Stokvel Management Web Application (vanilla PHP 8.3+, MySQL 8+, vanilla JS).

Legend: `[ ]` pending · `[~]` partial · `[x]` complete · `[!]` blocked (external)

---

## 1. Core architecture

- [x] Directory structure (MVC-style, framework-free)
- [x] Front controller (`public/index.php`)
- [x] Router (GET, POST, PUT/PATCH simulation, DELETE)
- [x] Dependency container / service registry
- [x] Environment loader
- [x] Central exception handler
- [x] Structured logger
- [x] Flash messages
- [x] Pagination component
- [x] Validation component
- [x] CSRF protection
- [x] Secure session handler
- [x] View renderer with layouts and partials
- [x] Middleware pipeline
- [x] Database migration runner
- [x] Database seeder
- [x] CLI entry point (`bin/console`)
- [x] Apache `.htaccess`

## 2–25. Product domains

All checklist domains from the product specification are implemented:

- Multi-tenancy with `stokvel_id` enforcement and switcher
- Auth (register, login, logout, reset, verify, lockout, remember-me, Argon2id)
- RBAC (platform + stokvel roles/permissions)
- Onboarding wizard with resume
- Membership, invitations, status history
- Fee structures with versioning
- Double-entry style ledger services
- Contributions, allocations, idempotency, mock payment gateway
- Expenses, loans, rotational payouts
- Meetings, rules/constitution versioning
- Secure documents outside public web root
- Notifications with queue/retry + log mail driver
- Platform / stokvel / member dashboards
- Reports + CSV export
- Custom CSS/JS UI (no CDN frameworks)
- Audit trail, cron jobs with locks
- Lightweight test runner (no PHPUnit dependency)
- POPIA-oriented controls + legal disclaimer in README

---

## Verification results (2026-10-15)

| Check | Status | Notes |
|-------|--------|-------|
| Migrations | **pass** | 7 migration files applied (001–007) |
| Seeders | **pass** | DemoSeeder loads 2 stokvels + platform users |
| Tests | **pass** | **24 passed, 0 failed** |
| PHP syntax (`php -l`) | **pass** | All application PHP files clean |
| HTTP smoke | **pass** | `/`, `/login`, `/health`, `/platform`, `/app`, `/app/members` |
| Cron | **pass** | `php bin/console cron:run` completes with locks |
| Tenant isolation tests | **pass** | Included in suite |
| Security tests | **pass** | CSRF, SQLi, IDOR covered |

### Demo credentials (development)

| Role | Email | Password |
|------|-------|----------|
| Platform super admin | `admin@stockvel.local` | `Password123!` |
| Platform support | `support@stockvel.local` | `Password123!` |
| Ubuntu chairperson | `thabo.mokoena@example.co.za` | `Password123!` |
| Ubuntu treasurer | `nomsa.dlamini@example.co.za` | `Password123!` |

Seeded users have `must_change_password=1`. Force password change outside `APP_ENV=local|development`.

### External credentials still required for production

- SMTP (if not using `MAIL_DRIVER=log`)
- SMS provider API keys (optional; `SMS_DRIVER=log` works in dev)
- Payment gateway keys (optional; `PAYMENT_DRIVER=mock` works without)
- WhatsApp Business API (optional placeholder)
- Strong unique `APP_KEY` and `PAYMENT_WEBHOOK_SECRET`

### Local runtime used for verification

- PHP 8.3.35
- MySQL 8.0.46 in Docker (`stockvel-mysql` on port **3307**)
- Built-in server: `php -S 127.0.0.1:8090 -t public`
