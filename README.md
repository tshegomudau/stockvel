# stockvel

Multi-tenant web app for South African **stokvels** (savings groups): memberships, contributions, fees, expenses, loans, rotational payouts, meetings, documents, approvals, and reports.

Vanilla PHP 8.3+, MySQL 8+, no Composer frameworks.

## Requirements

- **PHP** 8.3+ with `pdo_mysql`, `mbstring`, `fileinfo`, `openssl`, `intl`, `bcmath`, `json` (`curl` recommended)
- **MySQL** 8.0+ (InnoDB, `utf8mb4`)
- Optional: Apache 2.4+ with `mod_rewrite` (document root = `public/`)

Composer is **not** required. Autoloading is in `bootstrap/autoload.php`.

## New developer setup (5 minutes)

### 1. Clone and configure

```bash
cd stockvel
cp .env.example .env
```

Edit `.env`:

| Variable | Typical local value |
|----------|---------------------|
| `DB_HOST` / `DB_PORT` | `127.0.0.1` / `3306` |
| `DB_DATABASE` | `stockvel` |
| `DB_USERNAME` / `DB_PASSWORD` | your MySQL user |
| `APP_URL` | Prefer matching the browser host (e.g. `http://localhost`). Assets use the live request origin when available so CSP `self` works even if this differs. For Apache under `/projects/stovel/public/`, leave `APP_BASE_PATH` empty (auto-detected) or set it to `/projects/stovel/public`. Ensure `storage/sessions` is writable by the web user (`chmod 1777 storage/sessions`). |
| `APP_KEY` | any long random string |
| `MAIL_DRIVER` | `log` |
| `PAYMENT_DRIVER` | `mock` |

### 2. Create databases

```sql
CREATE DATABASE stockvel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE stockvel_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Grant your MySQL user access to both.

**Docker MySQL (optional):**

```bash
docker run -d --name stockvel-mysql \
  -e MYSQL_ROOT_PASSWORD=root \
  -e MYSQL_DATABASE=stockvel \
  -e MYSQL_USER=stockvel \
  -e MYSQL_PASSWORD=stockvel_pass \
  -p 3307:3306 \
  mysql:8.0 \
  --character-set-server=utf8mb4 \
  --collation-server=utf8mb4_unicode_ci
```

Then set `DB_PORT=3307` (and matching credentials) in `.env`.

### 3. One-command bootstrap

```bash
php bin/console setup
```

This creates writable `storage/*` directories, runs all migrations (`001`–`008`), and loads demo data.

Equivalent manual steps:

```bash
php bin/console migrate
php bin/console seed
```

### 4. Run the app

```bash
php bin/console serve 127.0.0.1 8090
# equivalent: php -S 127.0.0.1:8090 -t public public/router.php
```

Open [http://127.0.0.1:8090](http://127.0.0.1:8090). The `public/router.php` script serves CSS/JS as static files; do not point the built-in server at `index.php` alone or assets will 404.

## Demo accounts

Password for every seeded user: **`Password123!`**

| Email | Access |
|-------|--------|
| `admin@stockvel.local` | Platform super admin |
| `support@stockvel.local` | Platform support |
| `thabo.mokoena@example.co.za` | Ubuntu Savings Club — chairperson |
| `nomsa.dlamini@example.co.za` | Ubuntu — treasurer |
| `sipho.ndlovu@example.co.za` | Ubuntu — secretary |
| `lerato.pillay@example.co.za` | Ubuntu — ordinary member |
| `themba.zulu@example.co.za` | Masakhane Burial Society — chairperson |
| `patience.molefe@example.co.za` | Masakhane — treasurer |

More accounts live in `database/seeders/DemoSeeder.php`. Extended test-only accounts: `php bin/console seed:e2e --env=testing` (see `docs/END_TO_END_TEST_PLAN.md`).

`DemoSeeder` is safe to re-run: if demo data is already present it skips. To wipe and reload a **test** database only:

```bash
php bin/console db:reset --env=testing
php bin/console seed:e2e --env=testing
```

Never run `db:reset` against a production or shared development database — the command refuses non-test names.

## Console cheat sheet

```bash
php bin/console setup                 # storage + migrate + demo seed
php bin/console migrate               # pending migrations
php bin/console migrate:status        # applied vs pending
php bin/console migrate:rollback [n]
php bin/console seed                  # DemoSeeder
php bin/console seed:e2e --env=testing
php bin/console db:reset --env=testing
php bin/console test:all --env=testing
php bin/console serve [host] [port]
php bin/console cron:run
php bin/console help
```

Schema notes: `database/README.md`.

## Testing

Use a dedicated test database (name must contain `test` / `testing`):

```bash
cp .env.example .env.testing
# Set DB_DATABASE=stockvel_testing (and TEST_DB_* to match)
php bin/console db:reset --env=testing
php bin/console seed:e2e --env=testing
php bin/console test:all --env=testing
```

Latest verified suite status: `TEST_RESULTS.md` / `storage/test-artifacts/last-run.json`.

## Project layout

| Path | Role |
|------|------|
| `public/` | Web root (`index.php`, assets) |
| `app/` | Controllers, services, views, core |
| `database/migrations` | Schema versions `001`–`008` |
| `database/seeders` | Demo + E2E seeders |
| `bin/console` | CLI |
| `storage/` | Logs, sessions, documents (writable) |
| `tests/` | Lightweight TestRunner suites |
| `docs/` | E2E test plan |

## Cron (production)

```cron
*/10 * * * * cd /var/www/stockvel && /usr/bin/php bin/console cron:run >> storage/logs/cron.log 2>&1
```

## Production checklist

- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] Strong `APP_KEY`, least-privilege DB user
- [ ] HTTPS; `SESSION_SECURE=true`
- [ ] Document root = `public/` only
- [ ] Writable `storage/*`
- [ ] Cron configured
- [ ] Real mail/SMS (not `log` drivers)
- [ ] Rotate `PAYMENT_WEBHOOK_SECRET`
- [ ] POPIA / consent / retention reviewed

## Legal disclaimer

stockvel is tooling for voluntary savings groups — not legal, tax, or regulatory advice. Loan modules are record-keeping aids. Operators remain responsible for NCA, FIC/AML, and POPIA compliance.

## License

Proprietary — see repository owner for terms.
