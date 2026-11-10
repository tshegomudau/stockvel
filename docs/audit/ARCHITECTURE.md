# Architecture — Stokvel / Stovel

**Audit branch:** `audit/production-2026-11-10`  
**Baseline commit:** `03513f6`  
**Discovery date:** 2026-11-10

## Stack

| Layer | Choice |
|-------|--------|
| Language | PHP ≥ 8.3 (observed 8.3.36) |
| Framework | Custom vanilla MVC (not Laravel/Symfony) |
| HTTP | Front controller `public/index.php` → `App::run()` |
| Database | MySQL 8+ via PDO (`emulate_prepares=false`) |
| Composer | `phpmailer/phpmailer` only |
| Frontend | Server-rendered PHP views + `public/assets` CSS/JS (no npm SPA) |
| Locale | `Africa/Johannesburg`, ZAR, `en_ZA` |

## Entry points

- `public/index.php` — HTTP
- `public/cron.php` — token-gated web cron
- `public/install.php` — shared-hosting installer
- `bin/console` — migrate / seed / test / cron / serve
- `cron/run.php` — CLI cron wrapper

## Request pipeline

1. Autoload + `.env` (+ `.env.testing` overlay)
2. DI bindings (`bootstrap/register.php`)
3. Session start (`storage/sessions`)
4. `AuthService::hydrateTenantFromSession()`
5. `routes/web.php` → `Router::dispatch`
6. Middleware stacks → controller → `Response`

### Middleware stacks

| Alias | Stack |
|-------|--------|
| `$global` | Security headers |
| `$csrf` | CSRF |
| `$auth` | Auth |
| `$tenant` / `$app*` | Auth + Tenant |
| `$platform` | Auth + PlatformAuth |
| `$guest` | Guest (redirect if signed in) |

Route-level `PermissionMiddleware` / `RateLimitMiddleware` exist in code but are largely unused in `web.php`; controllers call `requirePermission()`.

## Core components

- **DI:** `App\Core\Container` (closures + reflection)
- **DB:** `App\Core\Database` with nested transactions/savepoints
- **Tenancy:** `TenantContext` (core + service) + `TenantMiddleware`
- **Authz:** `PermissionService` (tenant + platform catalogues)
- **Money:** `App\Support\MoneyMath` (bcmath preferred; see INITIAL_FINDINGS)
- **Migrations:** numbered PHP files via `Migrator` (001–023)
- **Jobs:** `CronRunner` file-locked tasks under `storage/cache`

## Surfaces

| Prefix | Audience |
|--------|----------|
| `/`, `/start-stokvel`, invites | Public / guest onboarding |
| `/platform/*` | Platform admins |
| `/app/*` | Stokvel committee / ops |
| `/member/*` | Ordinary members |
| `/account/*` | Get-started / suspended notice |
| `POST /webhooks/payments` | Payment webhooks (CSRF-exempt) |

## Payments

`PaymentGatewayInterface` → **`MockPaymentGateway` only**. No live PayFast/Ozow/Stripe integration in tree. Webhook path must verify HMAC before side effects.

## Deploy / CI

- **No** GitHub Actions / GitLab CI workflows found
- Shared hosting: `deploy_stokvel.sh`, `scripts/pack-shared-hosting.sh`, `docs/SHARED_HOSTING.md`
- Production rule for this audit: **never deploy automatically; never touch prod DB**
