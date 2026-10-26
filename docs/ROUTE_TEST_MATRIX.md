# Route Test Matrix

See also `docs/ROUTE_ACCESS_MATRIX.md` for middleware summary.

Inventory: `php scripts/list_routes.php` (≈323 registrations).

| Route pattern | Method | Module | Authentication | Permission | Tenant | UI / HTTP tested | Expected | Status |
|---|---|---|---|---|---|---|---|---|
| `/login` | GET/POST | Auth | Guest | — | N/A | Yes | 200 / redirect | Pass |
| `/logout` | POST | Auth | Auth + CSRF | — | N/A | Yes | Redirect | Pass |
| `/app` | GET | Dashboard | Tenant | Auth | Yes | Yes | 200 | Pass |
| `/app/members*` | * | Members | Tenant | `members.*` | Yes | Yes | 200/403 | Pass |
| `/app/contributions*` | * | Contributions | Tenant | `contributions.*` | Yes | Yes | 200 | Pass |
| `/app/funds*` | * | Funds | Tenant | `fees.manage` | Yes | Partial | 200 | Pass |
| `/app/fines*` `/auto` | * | Fines | Tenant | `fees.manage` | Yes | Auto deep E2E | 200 | Pass |
| `/app/approvals*` | * | Approvals | Tenant | `approvals.*` | Yes | Yes | 200 | Pass |
| `/app/meetings*` | * | Meetings | Tenant | `meetings.manage` | Yes | MeetingWorkflow | 200 | Pass |
| `/app/rules*` | * | Rules | Tenant | `rules.manage` | Yes | RulesWorkflow | 200 | Pass |
| `/app/reports*` `/trends` `/annual` | GET | Reports | Tenant | `reports.view` | Yes | Trends tests | 200 | Pass |
| `/app/settings*` | * | Settings | Tenant | `settings.manage` | Yes | Partial HTTP | 200 | **Partial** |
| `/app/support*` | * | Support | Tenant | support | Yes | SupportTicketTest | 200/422 | Pass |
| `/member/*` | * | Member portal | Tenant | membership | Yes | RoleMatrix | 200 | Pass |
| `/member/statement` | GET | Statements | Tenant | own/admin | Yes | StatementBalance | 200 | Pass |
| `/platform/*` | * | Platform | PlatformAuth | platform role | Optional | RoleMatrix | 200/403 | Pass |
| `/platform/audit` | GET | Audit | Platform + `audit_logs.view` | Platform only | N/A | AuditLogAccessTest | 200/403 | Pass |

Stale/placeholder customer routes for excluded integrations: **none found**.
