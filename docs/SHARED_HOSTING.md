# Temporary shared hosting (cPanel)

Use this when the host gives **cPanel** (File Manager, FTP, phpMyAdmin, Cron Jobs, and optionally **Git™ Version Control**) — often with little or no SSH. Prefer a VPS later for full CLI (`composer`, migrations, queues).

## What you need on the host

- PHP **8.3+** with `pdo_mysql`, `mbstring`, `openssl`, `json`, `fileinfo`, `bcmath` (`intl` and `curl` recommended)
- MySQL **8** (or MariaDB close to 8) with an empty database + user
- Apache `mod_rewrite` (almost always on)
- Ability to set the domain/subdomain **document root** to `…/public` (preferred)
- Note: shared hosts often disable `getmypid` / `gethostname`. Cron locking already falls back safely without them.

## Two ways to deploy

| Method | Use when | Avoid when |
|--------|----------|------------|
| **A. Packaged zip** (`scripts/pack-shared-hosting.sh`) | No Git in cPanel; first install; host has no Composer | You want frequent code pulls from git |
| **B. cPanel Git™ Version Control** | Host offers Git VC; you control a private remote; you can still place `vendor/` and protect `.env` / `storage/` | Public repo with secrets; no way to ship `vendor/`; document root cannot leave `public_html` |

**Recommendation:** Use **Git™ Version Control for code updates** if available, but treat the **first install** like the zip path (DB + `.env` + writable `storage/` + delete `install.php`). Do **not** rely on Git alone to produce a runnable site.

---

## Can cPanel Git™ Version Control be used?

**Yes — with conditions.** It is a good way to pull application code onto the server without FTP-zipping every release. It is **not** a full CI/CD replacement for this project.

### What Git VC is good for

- Cloning / pulling from GitHub, GitLab, Bitbucket, or another remote into a directory under your home (e.g. `/home/USER/stockvel`)
- Updating `app/`, `public/`, `routes/`, `database/migrations/`, etc. after you push from your laptop
- Keeping the live tree closer to a known git revision than manual File Manager uploads

### What Git VC does **not** do for stockvel

| Gap | Why it matters | What to do |
|-----|----------------|------------|
| **`vendor/` is not in git** (see `.gitignore`) | PHPMailer / Composer libs will be missing after clone | Run `composer install --no-dev` **on a machine that has Composer**, then upload `vendor/` once (FTP/File Manager), **or** include `vendor/` in the zip pack and copy it beside the git checkout. Do not commit secrets into the repo to “fix” this. |
| **No `.env` in git** | Clone has no DB credentials / `APP_KEY` / `CRON_TOKEN` | Create `.env` via browser installer or upload from `.env.example` filled in privately. Never commit `.env`. |
| **`storage/` runtime data** | Logs, sessions, uploads, install lock must survive deploys | Keep `storage/` and `public/uploads/` **outside** what you force-reset on pull, or restore permissions after pull. Do not `git clean -fdx` on the host. |
| **Migrations** | Pulling new migration files does not apply them | Prefer `php bin/console migrate` if SSH/CLI exists. Without SSH: use a maintenance window and a trusted one-off process, or apply via a controlled release that you have tested — do not expose ad-hoc migrate URLs publicly. |
| **Document root** | Git clone directory ≠ web root | Still point the domain at `…/stockvel/public` (or use the `public_html` rewrite fallback below). |
| **Private remotes** | cPanel must authenticate | Use a deploy key / HTTPS token with **read-only** access. Revoke if the host is compromised. |
| **Hooks / build** | cPanel Git “pull” does not run Composer or tests | Build `vendor/` elsewhere; keep production `.env` untouched by pull. |

### Verdict

| Scenario | Advice |
|----------|--------|
| Private repo + you can upload `vendor/` separately + document root → `public/` | **Use Git VC** for code; keep zip/installer for bootstrap |
| Only public GitHub clone, no Composer, no way to upload `vendor/` | **Do not use Git alone** — use the pack zip (ships `vendor/`) |
| Repo contains `.env` or production dumps | **Do not deploy that repo** — rotate secrets immediately |
| Frequent releases with schema changes and no SSH | Prefer zip releases you tested locally, or get SSH/migrate access before relying on Git pulls |

---

## Option A — Pack on your laptop (zip / no Git)

```bash
composer install --no-dev
chmod +x scripts/pack-shared-hosting.sh
./scripts/pack-shared-hosting.sh
```

This writes `dist/stockvel-shared-*.zip` (includes `vendor/` when present) and prints an **INSTALL TOKEN**. Keep that token private.

### Upload layout (pick one)

#### A1 — Best: document root = `public/`

1. Upload/extract the zip under your home, e.g. `/home/USER/stockvel/`.
2. In cPanel → **Domains** / **Subdomains**, set document root to `/home/USER/stockvel/public`.
3. Ensure `storage/` and `public/uploads/` are writable by the web user (File Manager → Permissions `775` or `755` as the host allows).

#### A2 — Fallback: everything under `public_html/`

1. Extract so you have `public_html/app`, `public_html/public`, `public_html/bootstrap`, etc.
2. Copy `deploy/public_html.htaccess` to `public_html/.htaccess` (rewrites into `public/`).
3. Visit `https://your-domain/` (rewrite) or `https://your-domain/public/` if rewrite is disabled.
4. If you use the `/public` URL path, set `APP_BASE_PATH=/public` in the installer.

---

## Option B — cPanel Git™ Version Control

### First-time setup

1. In cPanel → **Git™ Version Control** → **Create**:
   - **Clone URL:** your private remote (HTTPS with token, or SSH deploy key if the host supports it)
   - **Repository Path:** e.g. `/home/USER/stockvel` (prefer **outside** `public_html`)
   - **Branch:** `main` (or your release branch)
2. After clone succeeds, in **Domains** set document root to `/home/USER/stockvel/public`.
3. Upload **`vendor/`** (from a local `composer install --no-dev`) into `/home/USER/stockvel/vendor/` if it is missing.
4. Create MySQL DB + user in cPanel.
5. Either:
   - Drop a one-time `storage/install.token` and open `/install.php?token=…` (same as zip flow), **or**
   - Upload a completed `.env` (from `.env.example`) and ensure `storage/*` dirs exist and are writable, then run migrations if CLI is available.
6. Delete **`public/install.php`** after a successful install.
7. Confirm login, mail, and cron.

### Updating code later (pull)

1. Push tested commits from your laptop to the remote.
2. In cPanel Git VC → select the repo → **Update** / **Pull** (or “Pull or Deploy” if offered).
3. After pull:
   - Confirm `.env` is still present and unchanged.
   - Confirm `vendor/` still exists (pull must not delete it; it is untracked — avoid “force” cleans).
   - Re-check `storage/` and `public/uploads/` permissions.
   - Apply any new **database migrations** before relying on new features.
4. Smoke-test: login, one member page, `/health` if enabled, cron fetch.

### Safe Git VC practices for this app

- Deploy from a **private** repository only.
- Use a **read-only** deploy token/key limited to this repo.
- Never commit `.env`, `storage/install.token`, dumps, or ID documents.
- Prefer pulling into `/home/USER/stockvel` and pointing the vhost at `public/` — do not make the git root the public web root.
- Keep production uploads and logs on the server; do not wipe `storage/` or `public/uploads/` when updating.
- If a pull introduces PHP 8 / extension issues, check cPanel → **Select PHP Version** (need 8.3+ and the extensions listed above).

---

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

After install, the success screen shows a cron URL. In cPanel → **Cron Jobs**, add a job every 5–15 minutes that **fetches**:

`https://YOUR-DOMAIN/cron.php?token=CRON_TOKEN`

(or `wget -q -O - '…'` if the host offers shell cron without interactive SSH).

Cron locking works even when `getmypid` is disabled on the host.

## Mail / SMS / payments on shared hosting

- Zip pack includes `vendor/` (PHPMailer) when you run `composer install` before packing. Git clones need `vendor/` uploaded separately.
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
4. On the VPS: upload or `git clone`, point nginx/Apache at `public/`, import SQL, `composer install --no-dev`, `chmod` storage, set `APP_URL`, run `php bin/console migrate` for any newer migrations, point DNS.
5. Remove shared-hosting `install.php` / unused tokens; rotate `APP_KEY` / DB password if the shared host was untrusted.

## Troubleshooting

| Symptom | Check |
|--------|--------|
| 500 on all pages | PHP version; `storage/logs`; `.env` present; DB credentials |
| CSS/JS missing | `APP_URL` / `APP_BASE_PATH`; document root really `public/` |
| Installer “no token” | `storage/install.token` uploaded, or `INSTALL_TOKEN` in `.env` |
| Cannot write `.env` | Temporarily chmod app root writable, or upload `.env` via FTP then re-open installer |
| Blank page | Enable temporary `APP_DEBUG=true` in `.env` via File Manager, reload, then turn off |
| `Call to undefined function …\getmypid()` | Host disables PID functions; current `CronRunner` falls back — redeploy latest code |
| Class / PHPMailer missing after Git pull | `vendor/` missing — upload from local `composer install --no-dev` |
| Git pull wiped config | `.env` was never meant to be in the repo — restore from backup; do not `git clean` the app root |
| `PHPMailer SMTP failed: Could not connect to SMTP host` | **Not bad credentials** (those say “authenticate”). Shared host blocked outbound SMTP. See **SMTP on shared hosting** below. |

## SMTP on shared hosting (SMTP2GO)

Dev works / prod fails with **“Could not connect to SMTP host / Failed to connect to server”** almost always means the **host firewall blocked the port**, not wrong username/password.

1. In production `.env`, try alternate SMTP2GO ports (same username/password):

```env
MAIL_DRIVER=smtp2go
MAIL_HOST=mail-eu.smtp2go.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_PORT_FALLBACKS=587,465,8025,80
```

Other ports to try: `465` + `MAIL_ENCRYPTION=ssl`, or `8025` / `80` + `tls`.

2. Confirm PHP has `openssl` enabled (cPanel → Select PHP Version → Extensions).
3. If it connects but TLS fails, temporarily test `MAIL_SMTP_OPTIONS_INSECURE=true`, then fix the host CA bundle and turn it off.
4. Ask the host to allow outbound TCP to `mail-eu.smtp2go.com` / `mail.smtp2go.com` on 587, 465, 2525, 8025.
5. SMTP2GO must have the From domain verified — that affects delivery after connect, not “could not connect”.
6. Check `storage/logs` for which host:port was attempted; retry failed jobs after changing `.env`.

## Security checklist (temporary host)

- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] `install.php` deleted
- [ ] HTTPS + `SESSION_SECURE=true`
- [ ] Strong DB password; demo seed off for real members
- [ ] Cron URL token not shared publicly
- [ ] Git remote is private; deploy credentials are read-only and rotatable
- [ ] `.env` and `vendor/` present on server but **not** committed to the remote
