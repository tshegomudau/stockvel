# Route Access Matrix (approved phase)

Generated inventory from `routes/web.php` (≈323 method+path registrations).  
Middleware stacks: `$global`, `$guest`, `$auth`, `$appRead`/`$appMut` (tenant + CSRF), `$memberRead`/`$memberMut`, `$platform`.

| Area | Auth | Typical permission | Tenant | Notes | Status |
|---|---|---|---|---|---|
| Public `/`, `/health`, invitations | None / token | — | N/A | Landing + health JSON | Covered |
| Auth login/register/MFA/logout | Guest/Auth | — | N/A | CSRF on POSTs | Covered |
| `/app/*` stokvel admin | Auth + Tenant | Module RBAC | Required | `$appRead`/`$appMut` | Covered |
| `/member/*` portal | Auth + Tenant | Membership | Required | Pending memberships allowed where intended | Covered |
| `/platform/*` | Auth + PlatformAuth | Platform role | Optional inspect | No stokvel role escalation | Covered |
| `/platform/audit` | Platform + **`audit_logs.view`** | Platform only | N/A | Stokvel users → 403 | Covered (AuditLogAccessTest) |
| Fines `/app/fines`, `/auto`, `/bulk` | Auth | `fees.manage` | Yes | Auto preview/confirm | Covered |
| Reports `/app/reports`, `/trends`, `/annual` | Auth | `reports.view` | Yes | Forecast labelled | Covered |
| Funds `/app/funds` | Auth | `fees.manage` | Yes | Ledger | Covered |
| Approvals `/app/approvals` | Auth | `approvals.*` | Yes | Multi-approver | Covered |
| Settings `/app/settings*` | Auth | `settings.manage` | Yes | Needs full UI click evidence | Partial |
| Categories CRUD | Auth | `settings.manage` / module | Yes | Tenant-scoped | Covered |
| Support tickets | Auth | membership / platform | Yes | SupportTicketTest | Covered |

## Explicit denials verified

| Actor | Route | Expected |
|---|---|---|
| Chairperson | `GET /platform/audit` | 403 |
| Ordinary member | `GET /platform/audit` | 403 |
| Platform support | `GET /platform/audit` | 403 (no `audit_logs.view`) |
| Platform auditor / super admin | `GET /platform/audit` | 200 |

## Out of scope routes

No customer-facing SMS/WhatsApp/payment/bureau/marketplace routes are registered for this phase.

Full path listing: run `php scripts/list_routes.php`.
