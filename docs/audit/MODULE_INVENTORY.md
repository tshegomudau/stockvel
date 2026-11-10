# Module inventory

**Date:** 2026-11-10

## Domains

| Domain | Primary services | Controllers |
|--------|------------------|-------------|
| Auth / MFA / password | `Auth/*`, `PasswordPolicyService`, `MfaService` | `AuthController`, `MfaController` |
| Tenancy / RBAC | `TenantContext`, `PermissionService` | Switch + platform role controllers |
| Guest stokvel create | `StokvelCreationRequestService` | `RegisterStokvelController`, `StokvelClaimController`, `StokvelRequestController` |
| Membership / invites | `MembershipService`, `InvitationService` | `MemberController`, `InvitationController`, `InviteController` |
| Contributions / charges | `ContributionService`, `ChargeService`, `ContributionEditRequestService` | `ContributionController`, fee controllers |
| Ledger / COA | `LedgerService`, `ChartOfAccountsService` | (via finance flows) |
| Funds / fines / expenses | `FundService`, `FineService`, `ExpenseService` | Fund/Fine/Expense controllers |
| Loans / payouts | `LoanService`, `PayoutService` | Loan/Payout controllers |
| Approvals | `ApprovalService` | `ApprovalController` |
| Meetings / rules | `MeetingService`, `RuleService` | Meeting/Rule controllers |
| Documents / storage | `DocumentService`, `StorageManager` | Document/Media controllers |
| Notifications / mail | `Notification*`, `MailChannel`, `EmailTemplateService` | Notification + email template controllers |
| Platform admin | `PlatformSettingsService`, `ApplicationLogReader` | `Platform/*` |
| Reports / health | `ReportService`, `GroupHealthService`, `TrendsService` | Report/Health controllers |
| Cron | `CronRunner` | `public/cron.php` / console |
| Payments | `MockPaymentGateway`, `PaymentWebhookService` | `WebhookController` |

## Migrations (001–023)

Core auth → stokvels → finance → loans/payouts → meetings/docs → notifications/audit → schema alignments → product upgrades → SA modules (burial/grocery) → MFA/modules → funds/fines → multi-approver/goals → guest create + platform RBAC → contribution edit requests.

## Repositories

Thin set: `BaseRepository`, `UserRepository`, `StokvelRepository`, `MembershipRepository`, `AuditRepository`. Most domain logic uses Services + SQL.

## Route volume (approx.)

~380 registrations in `routes/web.php` (single file): ~249 `/app`, ~45 `/member`, ~54 `/platform`, plus public/auth/webhook.
