#!/usr/bin/env bash
# Build a zip you can upload via cPanel File Manager / FTP (no SSH on the host).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DIST="${ROOT}/dist"
STAMP="$(date +%Y%m%d-%H%M%S)"
NAME="stockvel-shared-${STAMP}"
STAGE="${DIST}/${NAME}"
TOKEN="$(openssl rand -hex 24 2>/dev/null || python3 -c 'import secrets; print(secrets.token_hex(24))')"

mkdir -p "${DIST}"
rm -rf "${DIST:?}/"*
mkdir -p "${STAGE}"

# Copy only known app trees (never rsync whole ROOT into dist/ — that deadlocks).
for dir in app bin bootstrap config cron database deploy docs public routes scripts storage tests; do
  if [[ -d "${ROOT}/${dir}" ]]; then
    mkdir -p "${STAGE}/${dir}"
  fi
done

cp -a "${ROOT}/app/." "${STAGE}/app/"
cp -a "${ROOT}/bin/." "${STAGE}/bin/"
cp -a "${ROOT}/bootstrap/." "${STAGE}/bootstrap/"
cp -a "${ROOT}/config/." "${STAGE}/config/"
cp -a "${ROOT}/database/." "${STAGE}/database/"
cp -a "${ROOT}/deploy/." "${STAGE}/deploy/"
cp -a "${ROOT}/docs/." "${STAGE}/docs/"
cp -a "${ROOT}/public/." "${STAGE}/public/"
cp -a "${ROOT}/routes/." "${STAGE}/routes/"
cp -a "${ROOT}/scripts/." "${STAGE}/scripts/"
[[ -d "${ROOT}/cron" ]] && cp -a "${ROOT}/cron/." "${STAGE}/cron/"
[[ -d "${ROOT}/tests" ]] && cp -a "${ROOT}/tests/." "${STAGE}/tests/"
[[ -d "${ROOT}/resources" ]] && mkdir -p "${STAGE}/resources" && cp -a "${ROOT}/resources/." "${STAGE}/resources/"
# PHPMailer (and any other documented Composer libs) — required on hosts without composer CLI
if [[ -d "${ROOT}/vendor" ]]; then
  mkdir -p "${STAGE}/vendor"
  cp -a "${ROOT}/vendor/." "${STAGE}/vendor/"
else
  echo "WARNING: vendor/ missing — run 'composer install' before packing (PHPMailer required for SMTP)." >&2
fi

# Root metadata (no secrets)
for f in README.md .env.example .htaccess .gitignore composer.json composer.lock; do
  [[ -f "${ROOT}/${f}" ]] && cp -a "${ROOT}/${f}" "${STAGE}/${f}"
done
# Helpful but optional docs
for f in BUG_FIX_LOG.md IMPLEMENTATION_STATUS.md; do
  [[ -f "${ROOT}/${f}" ]] && cp -a "${ROOT}/${f}" "${STAGE}/${f}"
done

# Fresh empty storage + uploads (do not ship local sessions/logs)
rm -rf "${STAGE}/storage" "${STAGE}/public/uploads"
mkdir -p \
  "${STAGE}/storage/logs" \
  "${STAGE}/storage/sessions" \
  "${STAGE}/storage/cache" \
  "${STAGE}/storage/documents" \
  "${STAGE}/storage/exports" \
  "${STAGE}/storage/mail" \
  "${STAGE}/public/uploads"
touch \
  "${STAGE}/storage/logs/.gitkeep" \
  "${STAGE}/storage/sessions/.gitkeep" \
  "${STAGE}/storage/cache/.gitkeep" \
  "${STAGE}/storage/documents/.gitkeep" \
  "${STAGE}/storage/exports/.gitkeep" \
  "${STAGE}/storage/mail/.gitkeep" \
  "${STAGE}/public/uploads/.gitkeep"

printf '%s\n' "${TOKEN}" > "${STAGE}/storage/install.token"
chmod 600 "${STAGE}/storage/install.token" || true

echo "Zipping ${NAME}…"
(
  cd "${DIST}"
  zip -qrX "${NAME}.zip" "${NAME}"
)
rm -rf "${STAGE}"

cat <<EOF

Packed: ${DIST}/${NAME}.zip

INSTALL TOKEN (keep private):
  ${TOKEN}

Next:
  1) Create MySQL DB + user in cPanel; note host/name/user/password.
  2) Upload zip → extract (prefer app outside public_html, or see docs/SHARED_HOSTING.md).
  3) Point domain/subdomain document root at the app's public/ folder when possible.
  4) Open https://YOUR-DOMAIN/install.php?token=${TOKEN}
  5) After success, delete public/install.php in File Manager.
  6) Add a cPanel cron to fetch /cron.php?token=… (token shown after install).

EOF
