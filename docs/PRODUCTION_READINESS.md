# Production readiness

**Date:** 2026-10-20  
**App:** Stockvel / Stokvel Management (vanilla PHP + MySQL)

## Modules tested (this cycle)

| Area | Notes |
|---|---|
| Auth / logout / CSRF | Automated suite |
| Dashboard / tenant switch | Role matrix + isolation tests |
| Contributions + CSV import/export | Template, preview row errors, import, report CSV |
| Expenses + categories | List/detail with category name + captured-by |
| Loans + loan products | Routes/permissions covered in role matrix |
| Payouts + payout categories | Burial/Grocery seeded as categories (migration 011) |
| Meetings + notes/minutes | WYSIWYG + `HtmlSanitizer` |
| Documents + categories | Managed categories; typed docs |
| Beneficiaries | Full CRUD + deactivate/reactivate |
| Rules | Create/version via `RuleService` |
| Support tickets | Field-level validation |
| Settings + Roles & permissions | Under `/app/settings` and `/app/settings/roles` |
| RBAC | Server-side `requirePermission` + `PermissionService` |
| Tenant isolation | Cross-tenant ID tests |
| Error pages | 403/404/419/422/429/500/503 → Dashboard / Go Back |
| XSS sanitiser | `HtmlSanitizer` unit tests |

## Changes made (high level)

1. **Router** — `POST` routes accept `_method` PUT/PATCH (fixes Settings/edit 404s).
2. **CSV** — Unified `CsvImportService` friendly headers, aliases, row-level errors; DI arg order fixed; PDO duplicate named params fixed.
3. **Tickets** — Field-specific validation messages + old input.
4. **Categories** — Payout, document, expense category management; burial/grocery as payout categories.
5. **Loan products** — Management UI wired to loan apply flows.
6. **Meetings** — Notes/agenda/minutes with shared WYSIWYG (`data-wysiwyg`) and server-side HTML sanitisation.
7. **Beneficiaries** — Stokvel-wide module with search/filter and share validation.
8. **RBAC Settings** — Per-stokvel role permission overrides in `stokvel_settings.settings_json.role_permissions`.
9. **Errors** — Authenticated users directed to dashboard; no stack traces in production.
10. **UX** — Structured list/detail for fees/expenses/payouts/rules/documents; currency/date helpers.

## RBAC structure

Permissions are codes enforced in controllers via `requirePermission()` / `PermissionService`.

### Stokvel roles (configurable overrides)

- chairperson (wildcard `*`)
- treasurer
- secretary
- committee_member
- ordinary_member
- auditor

### Representative permissions

See `App\Services\PermissionService::APP_PERMISSIONS`, including:

`members.*`, `contributions.*`, `expenses.manage`, `loans.manage`, `payouts.manage`,  
`beneficiaries.view|manage`, `meetings.manage|manage_minutes`, `rules.manage`,  
`documents.view|upload|manage_categories`, `reports.view|export`, `settings.manage`,  
`approvals.*`, `support.manage`, `burial.manage`, `grocery.manage`, `audit.view`.

Platform roles remain separate (`platform_super_admin`, `platform_support`, …).

## Migrations added

| File | Purpose |
|---|---|
| `009_product_upgrade_features.php` | Product upgrade foundations |
| `010_sa_market_modules.php` | SA market modules |
| `011_categories_and_meeting_minutes.php` | Payout/document categories, expense `is_active`, meeting agenda/minutes_html, beneficiary status, seed Burial/Grocery/etc. |

## Known limitations

- File-upload documents remain a Phase 2 / paid path; typed documents are production-ready.
- Email/SMS delivery depends on external providers — show configuration state when unset.
- Per-stokvel role overrides store permission lists in JSON settings (not a full custom-role table yet).
- Burial/Grocery cards remain as specialised workflows; payout cycles also use payout categories.
- Browser E2E is HTTP-client based in-repo; introduce Playwright/Cypress if deeper UI automation is required in CI.

## External configuration still required

- `APP_URL`, `APP_ENV=production`, `APP_DEBUG=false`
- MySQL credentials / migrations
- Encryption key for `SensitiveData` (ID numbers, etc.)
- Mail / SMS provider credentials for notifications
- Cron for notification jobs / charge generation (`bin/console` schedule)

## E2E scenarios exercised (automated)

- Login as treasurer/chairperson/secretary/member/platform
- Contribution CSV template + import preview errors + import
- Report CSV export without raw primary keys
- Cross-tenant membership denial
- Permission matrix (chairperson wildcard; member denied fees.manage)
- Meeting show with attendance
- Encrypted member change-request ID
- HtmlSanitizer XSS stripping
- MoneyMath / loan schedule (no float money)

## Commands

```bash
php bin/console db:reset --env=testing
php bin/console seed:e2e --env=testing
php bin/console test:all --env=testing
```

Latest suite result this cycle: **76 passed, 0 failed** (`php bin/console test:all --env=testing`).
