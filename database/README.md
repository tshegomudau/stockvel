# Database

Migrations and seeders for stockvel. Apply with `php bin/console migrate` and `php bin/console seed` (or `php bin/console setup`).

## Migrations (order matters)

| File | Purpose |
|------|---------|
| `001_create_core_tables.php` | Users, profiles, auth tokens, login attempts, rate limits |
| `002_create_stokvel_tables.php` | Tenants, settings, onboarding, RBAC, memberships, invitations |
| `003_create_finance_tables.php` | Fees, charges, payments, ledger, approval_requests, expenses, webhooks |
| `009_product_upgrade_features.php` | Support tickets, encrypted ID, typed docs, member change requests, settings/meeting notes |
| `010_sa_market_modules.php` | Burial claims + grocery cycles (SA market parity) |
| `004_create_loans_payouts.php` | Loan products/applications, payout cycles, distributions |
| `005_create_meetings_docs.php` | Meetings, rules, folders, documents |
| `006_create_notifications_audit.php` | Notifications, notification_jobs, audit_logs, cron locks |
| `007_align_service_schema.php` | Additive columns for COA sub_types, fee/loan/expense service fields |
| `008_create_approvals_reconcile_ack.php` | Meeting acknowledgements, bank reconciliation tables |

Each file returns `['up' => string[], 'down' => string[]]`. The migrator records applied files in `migrations`.

```bash
php bin/console migrate
php bin/console migrate:status
php bin/console migrate:rollback 1
```

## Seeders

| Seeder | Command | Use when |
|--------|---------|----------|
| `DemoSeeder` | `php bin/console seed` | Local development demo data |
| `E2ETestSeeder` | `php bin/console seed:e2e --env=testing` | Full test account matrix (test DB only) |

`DemoSeeder` is **idempotent**: if `admin@stockvel.local` already exists it skips. To reload demo data on a **test** database:

```bash
php bin/console db:reset --env=testing
php bin/console seed:e2e --env=testing
```

`db:reset` refuses databases whose name does not look like a test database.

## Fresh local database (manual)

```sql
CREATE DATABASE stockvel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE stockvel_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Point `.env` at `stockvel`, then `php bin/console setup`.
