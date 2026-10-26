# Production Gap Analysis (approved phase)

Last updated: 2026-10-26

Statuses below reflect **workflow-tested** readiness for the approved self-contained stokvel management phase.

Legend: COMPLETE · PARTIAL · BROKEN · NOT TESTED · OUT OF SCOPE

## Deferred / reclassified (not production blockers)

| Item | Classification | Notes |
|---|---|---|
| SMS gateway | OUT OF SCOPE — FUTURE PHASE | Explicitly excluded |
| WhatsApp Business API | OUT OF SCOPE — FUTURE PHASE | Explicitly excluded |
| Bank / EFT / DebiCheck / payments | OUT OF SCOPE — FUTURE PHASE | Explicitly excluded |
| Credit bureau / fraud / KYC / insurance / FSP | OUT OF SCOPE — FUTURE PHASE | Explicitly excluded |
| Marketplace | OUT OF SCOPE — FUTURE PHASE | Explicitly excluded |
| Knowledge Base | OPTIONAL / FUTURE ENHANCEMENT | Not in production navigation |
| Interest allocation | FUTURE ENHANCEMENT | Not required for current workflows |
| Installable PWA | FUTURE ENHANCEMENT | No meaningful PWA infra; **mobile responsiveness remains mandatory** |
| Executive handover UI | FUTURE ENHANCEMENT (post-core) | Implement only after P0/P1 clear |

## Core modules

| Module | Status | Evidence / notes |
|---|---|---|
| Authentication | COMPLETE | Login/MFA/CSRF tests; RoleMatrix HTTP |
| Dashboard | COMPLETE | Real KPIs; no fake bank/credit cards |
| Stokvel Management | COMPLETE | Settings GET/save/validation/RBAC browser + HTTP |
| Members | COMPLETE | Invite/approve/import; mobile cards |
| Beneficiaries | PARTIAL | Core CRUD works; report polish optional |
| Users (stokvel) | COMPLETE | Via memberships/roles |
| Roles | COMPLETE | Role matrix + settings roles |
| Permissions | COMPLETE | Server-side RBAC; platform vs tenant separated |
| Contributions | COMPLETE | Capture/bulk/import; payment methods aligned to service |
| Contribution Obligations | PARTIAL | Charges exist; member schedule UI polish |
| Joining Fees | PARTIAL | Charge-on-approve; anniversary job optional |
| Expenses | COMPLETE | Category FK |
| Expense Categories | COMPLETE | Tenant CRUD + CategoryModulesTest |
| Admin Pocket | COMPLETE | Fund reconciliation tests |
| Funds/Pockets | COMPLETE | Ledger + negative guard |
| Payouts | COMPLETE | Cycle/schedule modules |
| Payout Categories | COMPLETE | Tenant CRUD |
| Loans | COMPLETE | LoanLifecycleTest: apply→approve→disburse→partial/full settle + purpose FK |
| Loan Products | COMPLETE | Configurable products |
| Loan Purposes | COMPLETE | Tenant catalogue + deactivate; migration 019 |
| Repayments | COMPLETE | Covered in lifecycle (zero rejected; partial; settle) |
| Fines | COMPLETE | Issue/waive/bulk |
| Automatic Fines | COMPLETE | Preview + idempotent deep E2E |
| Meetings | COMPLETE | MeetingWorkflowTest |
| Attendance | COMPLETE | Finalised attendance rules for auto-fines |
| Minutes | COMPLETE | WYSIWYG + acknowledge |
| Rules | COMPLETE | RulesWorkflowTest |
| Constitution | PARTIAL | Module present; browser polish |
| Documents | COMPLETE | Tenant categories required (no hard-coded select) |
| Document Categories | COMPLETE | Tenant CRUD |
| Announcements | PARTIAL | Module present; deepen read/unread E2E |
| Voting | PARTIAL | Meeting resolutions/votes; quorum polish |
| Resolutions | PARTIAL | Via meetings |
| Approvals | COMPLETE | Multi-approver unit + inbox |
| Multi-Approval | COMPLETE | Threshold policies |
| Support Tickets | COMPLETE | SupportTicketTest |
| Statements | COMPLETE | Ranges/CSV/balance; FY uses year_end_month |
| Reports | COMPLETE | Index + typed reports |
| Trends | COMPLETE | Trailing average labelled Forecast |
| Annual Summary | COMPLETE | FY from year_end_month; HTTP regression; disclaimer |
| CSV Import | COMPLETE | Contributions/arrears/members |
| CSV Export | COMPLETE | Reports export |
| Settings | COMPLETE | Browser save + HTTP empty-name/RBAC |
| Platform Admin | COMPLETE | Middleware + console |
| Audit Logs | COMPLETE | **Platform-only** + `audit_logs.view` |
| Error Pages | COMPLETE | Auth users: Go Back + Dashboard; app layout when logged in |

## Known open items (non-excluded)

| Severity | Item |
|---|---|
| P2 | Loan committee approve/disburse/repay UI still needs deeper browser click-through (service lifecycle proven) |
| P2 | Beneficiary report download polish |
| P2 | Contribution member-facing schedule UI |
| P2 | Fee frequency select remains schedule cadence constants (acceptable system states) |
| P2 | Executive handover UI deferred |
| P2 | Additional list pages may still rely on controlled table scroll (members now card-responsive) |
| P3 | Knowledge Base / PWA deferred |

No open P0/P1 for the approved phase after this pass. Remaining gaps are P2 polish. Readiness: **BETA READY** pending broader multi-role browser matrix coverage beyond the scenarios evidenced in `E2E_TEST_MATRIX.md`.
