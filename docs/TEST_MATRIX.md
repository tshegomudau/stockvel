# TEST_MATRIX

**Updated:** 2026-10-20  
**Status legend:** `PASS` = tested and working · `PARTIAL` = loads / partial workflow · `FAIL` = known broken · `TODO` = not yet verified this cycle

| Module | Create | View | Edit | Delete | Validation | RBAC | Tenant Isolation | E2E | Status |
|---|---|---|---|---|---|---|---|---|---|
| Auth / Login | — | PASS | — | — | PASS | PASS | — | PASS | PASS |
| Dashboard | — | PASS | — | — | — | PASS | PASS | PASS | PASS |
| Members | invite PASS | PASS | PARTIAL | soft PASS | PARTIAL | PASS | PASS | PARTIAL | PARTIAL |
| Beneficiaries | PASS | PASS | PASS | soft/hard PASS | PASS | PASS | PASS | PARTIAL | PASS |
| Contributions | PASS | PASS | — | — | PASS | PASS | PASS | PASS | PASS |
| CSV import | PASS | — | — | — | PASS | PASS | PASS | PASS | PASS |
| CSV export (reports) | — | PASS | — | — | — | PASS | PASS | PASS | PASS |
| Fees | PARTIAL | PASS | PASS | PARTIAL | PARTIAL | PASS | PASS | PARTIAL | PARTIAL |
| Expenses | PASS | PASS | PARTIAL | PARTIAL | PARTIAL | PASS | PASS | PASS | PASS |
| Expense categories | PASS | PASS | PASS | soft PASS | PARTIAL | PASS | PASS | PARTIAL | PASS |
| Loans | PARTIAL | PASS | PARTIAL | PARTIAL | PARTIAL | PASS | PASS | PARTIAL | PARTIAL |
| Loan products | PASS | PASS | PASS | TODO | PARTIAL | PASS | PASS | PARTIAL | PASS |
| Payouts | PASS | PASS | PASS | PARTIAL | PARTIAL | PASS | PASS | PARTIAL | PASS |
| Payout categories | PASS | PASS | PASS | activate PASS | PARTIAL | PASS | PASS | PARTIAL | PASS |
| Burial claims | PARTIAL | PASS | — | — | PARTIAL | PASS | PASS | PARTIAL | PARTIAL |
| Grocery cycles | PARTIAL | PASS | — | — | PARTIAL | PASS | PASS | PARTIAL | PARTIAL |
| Meetings | PASS | PASS | PASS | soft PASS | PASS | PASS | PASS | PARTIAL | PASS |
| Meeting notes/minutes | PASS | PASS | PASS | — | PASS | PASS | PASS | PARTIAL | PASS |
| Rules | PASS | PASS | PASS | soft PASS | PASS | PASS | PASS | PASS | PASS |
| Documents | PASS | PASS | PASS | — | PARTIAL | PASS | PASS | PARTIAL | PASS |
| Document categories | PASS | PASS | PASS | activate PASS | PARTIAL | PASS | PASS | PARTIAL | PASS |
| Reports | — | PASS | — | — | — | PASS | PASS | PASS | PASS |
| Approvals | — | PASS | action PASS | — | — | PASS | PASS | PASS | PASS |
| Support tickets | PASS | PASS | reply PARTIAL | — | PASS | PARTIAL | PASS | PASS | PASS |
| Settings | — | PASS | PASS | — | PARTIAL | PASS | PASS | PASS | PASS |
| Roles & permissions (stokvel) | — | PASS | PASS | — | PARTIAL | PASS | PASS | PASS | PASS |
| Platform users/roles | PARTIAL | PASS | PARTIAL | — | PARTIAL | PASS | — | PARTIAL | PARTIAL |
| Notifications | — | PASS | — | — | — | PARTIAL | PASS | PARTIAL | PARTIAL |
| Error pages | — | PASS | — | — | — | — | — | PASS | PASS |
| Html / XSS sanitiser | — | — | — | — | PASS | — | — | PASS | PASS |

Automated suite baseline this cycle: **76 tests** on `stockvel_testing` (`php bin/console test:all --env=testing`).

Do not mark PASS unless the workflow was actually exercised (HTTP or service-level automated test, or documented manual E2E).
