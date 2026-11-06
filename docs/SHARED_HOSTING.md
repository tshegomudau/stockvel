# Temporary shared hosting (no SSH)

Use this when the host only gives **cPanel / FTP / File Manager / phpMyAdmin** — no terminal. Plan to export MySQL + files later and move to a VPS.

## What you need on the host

- PHP **8.3+** with `pdo_mysql`, `mbstring`, `openssl`, `json`, `fileinfo`, `bcmath` (`intl` recommended)
- MySQL **8** (or MariaDB close to 8) with an empty database + user
- Apache `mod_rewrite` (almost always on)
- Ability to set the domain/subdomain **document root** to `…/public` (preferred)

## Pack on your laptop (has terminal)

```bash
chmod +x scripts/pack-shared-hosting.sh
./scripts/pack-shared-hosting.sh
```

This writes `dist/stockvel-shared-*.zip` and prints an **INSTALL TOKEN**. Keep that token private.

## Upload layout (pick one)

### A — Best: document root = `public/`

1. Upload/extract the zip under your home, e.g. `/home/USER/stockvel/`.
2. In cPanel → Domains / Subdomains, set document root to `/home/USER/stockvel/public`.
3. Ensure `storage/` and `public/uploads/` are writable by the web user (File Manager → Permissions `775` or `755` as the host allows).

### B — Fallback: everything under `public_html/`

1. Extract so you have `public_html/app`, `public_html/public`, `public_html/bootstrap`, etc.
2. Copy `deploy/public_html.htaccess` to `public_html/.htaccess` (rewrites into `public/`).
3. Visit `https://your-domain/` (rewrite) or `https://your-domain/public/` if rewrite is disabled.
4. If you use the `/public` URL path, set `APP_BASE_PATH=/public` in the installer.

## Install (browser)

1. Create the MySQL database + user in cPanel; grant the user ALL on that DB.
2. Open:

   `https://YOUR-DOMAIN/install.php?token=INSTALL_TOKEN`

3. Fill APP_URL (`https://…`, no trailing slash), DB fields, optional demo seed.
4. Submit. On success:
   - Delete **`public/install.php`** in File Manager immediately.
   - Change any demo passwords if you seeded.
5. Confirm login works.

`storage/install.lock` prevents re-running the installer. Do not leave `install.php` on a public site.

## Cron without SSH

After install, the success screen shows a cron URL. In cPanel → Cron Jobs, add a job every 5–15 minutes that **fetches**:

`https://YOUR-DOMAIN/cron.php?token=CRON_TOKEN`

(or `wget -q -O - '…'` if the host offers shell cron without interactive SSH).

## Mail / SMS / payments on shared hosting

- Pack zip already includes `vendor/` (PHPMailer) when you run `composer install` before packing.
- For real email, edit `.env` after install:

```env
MAIL_DRIVER=smtp2go
MAIL_FROM_ADDRESS=noreply@your-verified-domain.co.za
MAIL_FROM_NAME=stockvel
MAIL_HOST=mail.smtp2go.com
MAIL_PORT=2525
MAIL_ENCRYPTION=tls
MAIL_USERNAME=...
MAIL_PASSWORD=...
```

- Keep `SMS_DRIVER=log` and `PAYMENT_DRIVER=mock` until those providers are configured.
- Until SMTP is set, `MAIL_DRIVER=log` writes under `storage/mail`.

## Later: migrate to a VPS

1. Put the site in maintenance (or take it offline).
2. Export MySQL via phpMyAdmin (SQL dump).
3. Download the app tree **including** `.env`, `storage/` (except huge caches if empty), and `public/uploads/`.
4. On the VPS: upload, point nginx/Apache at `public/`, import SQL, `chmod` storage, set `APP_URL`, run `php bin/console migrate` for any newer migrations, point DNS.
5. Remove shared-hosting `install.php` / unused tokens; rotate `APP_KEY` / DB password if the shared host was untrusted.

## Troubleshooting

| Symptom | Check |
|--------|--------|
| 500 on all pages | PHP version; `storage/logs`; `.env` present; DB credentials |
| CSS/JS missing | `APP_URL` / `APP_BASE_PATH`; document root really `public/` |
| Installer “no token” | `storage/install.token` uploaded, or `INSTALL_TOKEN` in `.env` |
| Cannot write `.env` | Temporarily chmod app root writable, or upload `.env` via FTP then re-open installer |
| Blank page | Enable temporary `APP_DEBUG=true` in `.env` via File Manager, reload, then turn off |

## Security checklist (temporary host)

- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] `install.php` deleted
- [ ] HTTPS + `SESSION_SECURE=true`
- [ ] Strong DB password; demo seed off for real members
- [ ] Cron URL token not shared publicly
