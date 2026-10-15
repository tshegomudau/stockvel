# Stovel

Stovel is a multi-tenant web application for managing South African **stokvels** (member savings groups): memberships, contributions, fees, expenses, loans, rotational payouts, meetings, documents, approvals, and financial reports.

## Requirements

- **PHP** 8.3 or newer
- **MySQL** 8.0+ (InnoDB, `utf8mb4`)
- **Apache** 2.4+ with `mod_rewrite` (document root must point at `public/`)
- PHP extensions: `pdo_mysql`, `mbstring`, `fileinfo`, `openssl`, `intl`, `bcmath`, `json`, `curl` (recommended for outbound integrations)

Optional for development:

- Docker (MySQL example below)
- Composer is **not** required; the app uses a lightweight PSR-4 autoloader in `bootstrap/autoload.php`.

## Quick start

### 1. Database (Docker example)

```bash
docker run -d --name stovel-mysql \
  -e MYSQL_ROOT_PASSWORD=root \
  -e MYSQL_DATABASE=stovel \
  -e MYSQL_USER=stovel \
  -e MYSQL_PASSWORD=stovel_pass \
  -p 3307:3306 \
  mysql:8.0 \
  --character-set-server=utf8mb4 \
  --collation-server=utf8mb4_unicode_ci
```

### 2. Environment

```bash
cp .env.example .env
# Edit DB_* values, APP_KEY, and mail/SMS drivers as needed.
```

Key variables (see `.env.example` for the full list):

| Variable | Purpose |
|----------|---------|
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | MySQL connection |
| `APP_URL` | Canonical URL (links, cookies) |
| `APP_KEY` | Application secret (rotate in production) |
| `MAIL_DRIVER=log` | Log mail to `storage/mail` in dev |
| `PAYMENT_WEBHOOK_SECRET` | Verify payment webhooks |

### 3. Install schema & demo data

```bash
php bin/console migrate
php bin/console seed
```

### 4. Run locally

```bash
php bin/console serve 127.0.0.1 8090
# or: php -S 127.0.0.1:8090 -t public
```

Open `http://127.0.0.1:8090` (set `APP_URL` to match).

Apache production: set `DocumentRoot` to the `public/` directory. A root `.htaccess` denies direct access to application code outside `public/`.

## Console commands

```bash
php bin/console migrate              # Run pending migrations
php bin/console migrate:rollback [n] # Roll back migrations
php bin/console seed                 # DemoSeeder
php bin/console test                 # Test suite (TestRunner)
php bin/console cron:run             # All scheduled tasks
php bin/console notifications:process
php bin/console charges:generate
php bin/console charges:overdue
php bin/console penalties:calculate
php bin/console serve [host] [port]
```

## Demo accounts

After `php bin/console seed`, all demo users share password **`Password123!`** (see `database/seeders/DemoSeeder.php`).

| Email | Role |
|-------|------|
| `admin@stovel.local` | Platform super admin |
| `support@stovel.local` | Platform support |
| `thabo.mokoena@example.co.za` | Ubuntu Stokvel chairperson |
| `nomsa.dlamini@example.co.za` | Ubuntu Stokvel treasurer |
| `sipho.ndlovu@example.co.za` | Ubuntu Stokvel secretary |
| `lerato.pillay@example.co.za` | Ubuntu ordinary member |
| `themba.zulu@example.co.za` | Masa Stokvel chairperson |
| `patience.molefe@example.co.za` | Masa Stokvel treasurer |

Additional seeded members use `@example.co.za` addresses listed in `DemoSeeder.php`.

## Application structure

- `public/` — web root (`index.php`, assets)
- `app/Core` — router, database, session, views
- `app/Controllers` — HTTP controllers (auth, stokvel admin, member portal, platform)
- `app/Services` — domain logic (finance, loans, payouts, notifications)
- `app/Views` — PHP templates
- `database/migrations` — schema versions
- `storage/` — logs, sessions, uploads, exports (must be writable)
- `cron/run.php` — optional CLI cron entry (also use `bin/console cron:run`)

## Cron (production)

Run every 5–15 minutes as the web server user:

```cron
*/10 * * * * cd /var/www/stovel && /usr/bin/php bin/console cron:run >> storage/logs/cron.log 2>&1
```

Tasks include charge generation, overdue marking, penalty calculation, notification delivery, payout processing, token expiry, and failed-job detection.

## Backups

1. **MySQL**: nightly logical backup, e.g. `mysqldump --single-transaction stovel > stovel-$(date +%F).sql`
2. **Files**: archive `storage/documents`, `storage/exports`, and `.env` (secure off-site storage)
3. Test restores quarterly; keep backups encrypted at rest.

## Production checklist

- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] Strong `APP_KEY`, unique DB credentials, least-privilege DB user
- [ ] HTTPS only; `SESSION_SECURE=true`
- [ ] Document root = `public/` only
- [ ] Writable `storage/*` with correct ownership
- [ ] Cron configured; monitor `storage/logs`
- [ ] Real SMTP/SMS providers configured (not `log` drivers)
- [ ] Rotate `PAYMENT_WEBHOOK_SECRET` and payment API keys
- [ ] POPIA: privacy policy, consent records, data retention aligned with `DOCUMENT_RETENTION_DAYS`

## Legal & compliance disclaimer

Stovel is **software tooling** for voluntary savings groups and their administrators. It is **not** legal, tax, or regulatory advice.

- **National Credit Act (NCA)**: If your stokvel offers credit to members or the public in a way that constitutes “credit agreements” under the NCA, you may need NCR registration and compliant documentation. Stovel’s loan modules are record-keeping aids only.
- **FIC / AML**: Collecting and pooling member funds may trigger anti-money laundering obligations depending on structure and partners. Consult a compliance professional.
- **POPIA**: You are responsible for lawful processing of personal information (member ID numbers, contact details, financial data), security safeguards, breach notification, and data subject rights.

Operators must obtain independent legal and accounting advice before relying on reports or workflows for regulatory filings.

## Testing

```bash
php bin/console test
```

Tests bootstrap migrations and demo seed when needed, run in transactions where appropriate, and integrate via `tests/TestRunner.php`.

## License

Proprietary — see repository owner for terms.
