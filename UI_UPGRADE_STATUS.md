# UI Upgrade Status

## Root cause of missing / broken styling

1. **Absolute `APP_URL` mismatch** — layouts emitted fully-qualified asset URLs from `APP_URL`. When the browser host/port differed from `APP_URL` (historically `:8080` vs server on `:8090`), CSS/JS requests hit the wrong origin and pages rendered as plain HTML.
2. **No `APP_BASE_PATH` support** — subdirectory installs could not prefix asset and route URLs correctly.
3. **CSS/HTML class drift** — layouts used `app-layout` / `nav-link` while older CSS rules targeted `layout-app` / `sidebar-link`, so large parts of the stylesheet never applied.
4. **CSP blocked Google Fonts** — `@import` of `fonts.googleapis.com` failed under `font-src 'self'`, leaving typography inconsistent.

## Infrastructure

- [x] `url()`, `asset()`, `route()`, `base_path_uri()` with `APP_BASE_PATH` + cache busting (`ASSET_VERSION` / `filemtime`)
- [x] `.env.example` (`APP_BASE_PATH`, `ASSET_VERSION`)
- [x] `public/.htaccess` — existing files served directly; front controller; MIME types; Authorization passthrough
- [x] Root `.htaccess` deny source access (unchanged)

## Design system CSS

- [x] `variables.css`, `reset.css`, `base.css`, `layout.css`, `components.css`
- [x] `forms.css`, `tables.css`, `auth.css`, `dashboard.css`, `responsive.css`
- [x] No CDN / Google Fonts (system font stack)

## Layouts & components

- [x] App shell (sidebar, topbar, mobile drawer, stokvel switcher, user menu)
- [x] Guest/auth shell
- [x] Partials: `styles`, `nav`, `page-header`, `stat-card`, `flash`, `empty`, `pagination`, `breadcrumbs`

## Screens upgraded

- [x] Auth: login, register, forgot, reset, verify-notice
- [x] Public home + invitation layout via guest shell
- [x] Dashboards: platform, stokvel (real data + SVG chart), member
- [x] Members, contributions, fees, expenses, loans, payouts
- [x] Meetings, rules, documents, reports, notifications, approvals, settings
- [x] Onboarding wizard with step indicators

## Verification (2026-10-16)

| Check | Result |
|-------|--------|
| CSS files HTTP 200 | Pass (all 10 modules) |
| CSS `Content-Type: text/css` | Pass |
| JS HTTP 200 + `application/javascript` | Pass |
| Asset URLs include cache-bust `?v=` | Pass |
| Nested route `/app` loads stylesheets | Pass |
| PHP syntax | Pass |
| Tests | **24 passed, 0 failed** |
| No Google Fonts dependency | Pass |

### Asset URL strategy

- `APP_URL` = scheme + host (optional port)
- `APP_BASE_PATH` = subdirectory prefix (empty for root installs / `php -S -t public`)
- `asset('css/…')` → `{APP_URL}{BASE}/assets/css/…?v=…`
- Layouts load modular CSS via `partials/styles.php` (not a single inlined blob)

### Apache

Document root must be `public/`. Example:

```apache
DocumentRoot /var/www/stockvel/public
<Directory /var/www/stockvel/public>
    AllowOverride All
    Require all granted
</Directory>
```

For subdirectory installs set `APP_BASE_PATH` accordingly and ensure `RewriteBase` matches.

## Remaining genuine notes

- Some create/edit forms still use older markup but inherit global form/button/card styles from the design system.
- Browser visual QA on physical devices was not available in this environment; verification used `curl` + PHP syntax + test suite.
- Production still needs real SMTP/SMS/payment credentials if those channels are enabled.
