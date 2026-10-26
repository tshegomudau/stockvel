# Configuration model

## Principles

- **System states** (Draft, Pending, Approved, Rejected, Active, Cancelled, …) are application-controlled constants/enums.
- **Business configuration** (expense categories, fine types, meeting types, funds, payout categories, …) is **tenant-scoped** and user-manageable.
- Seed values are starters only; authorised users may add/edit/deactivate.
- Prefer **active/inactive** over hard deletes when a value has been used historically.
- Historical financial rows keep valid foreign keys (and optional name snapshots where implemented).

## Configurable entities (examples)

| Entity | Table / area | Scoped by |
|---|---|---|
| Expense categories | `expense_categories` | stokvel |
| Payout categories | `payout_categories` | stokvel |
| Document categories | `document_categories` | stokvel |
| Fee categories | `fee_categories` | stokvel |
| Meeting categories/types | `meeting_categories` | stokvel |
| Rule categories | `rule_categories` | stokvel |
| Fine types | `fine_types` | stokvel |
| Internal funds/pockets | `stokvel_funds` | stokvel |
| Loan products | `loan_products` | stokvel |
| Loan purposes | `loan_purposes` | stokvel |
| Stokvel types (platform catalogue) | `stokvel_types` | platform |
| Beneficiary relationships | migration 017+ | stokvel where present |
| Approval policies | `approval_policies` | stokvel |
| Saving goals | `saving_goals` | stokvel |

## System states (not tenant-editable lists)

Membership: invited, applied, pending, active, suspended, exited, rejected  
Approvals: pending, approved, rejected  
Meetings: draft/scheduled/completed/cancelled (as implemented)  
Fines: outstanding, partially_paid, paid, waived, cancelled  
Funds transactions: credit / debit  
Announcements: draft, published, expired  

## Deletion policy

1. If never referenced → hard delete allowed.
2. If referenced → deactivate (`is_active = 0`); hide from new forms; keep labels on history.

## Permissions

Configuration screens require appropriate RBAC (e.g. `settings.manage`, `fees.manage`, category-specific permissions where defined). Server-side checks are mandatory.

## External / regulated services

Out of scope for this phase. No customer-facing provider configuration, Coming Soon, or faux balances/scores.
