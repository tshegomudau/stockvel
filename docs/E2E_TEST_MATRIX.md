# E2E Test Matrix (approved phase)

Automated suite baseline: **106 passed, 0 failed, 0 blocked** (2026-10-26).

| Module | Scenario | Role | Desktop | Mobile | RBAC | Tenant | Result |
|---|---|---|---|---|---|---|---|
| Auth | Login treasurer → contributions | Treasurer | Auto HTTP | Browser login | Yes | Yes | Pass |
| Auth | Ordinary member denied fees | Member | Auto HTTP | — | Yes | Yes | Pass |
| Members | Chair loads members | Chair | Auto HTTP | Cards @390px | Yes | Yes | Pass |
| Tenant | Stokvel B isolated dashboard | Chair B | Auto HTTP | — | Yes | Yes | Pass |
| Rules | Create/edit/publish | Secretary | Auto HTTP | — | Yes | Yes | Pass |
| Support | Valid create + missing subject | Member | Auto HTTP | — | Yes | Yes | Pass |
| Support | Cross-tenant ticket denied | Member | Auto HTTP | — | Yes | Yes | Pass |
| Approvals | Maker-checker self-approval blocked | Committee | Auto | — | Yes | Yes | Pass |
| Approvals | Multi-approver threshold pending | Unit | Auto | — | Yes | Yes | Pass |
| Funds | Balance = credits − debits; no negative | Treasurer | Auto | — | — | Yes | Pass |
| Fines | Issue + waive | Fees | Auto | — | Yes | Yes | Pass |
| Auto-fines | Grace window / overdue / paid / idempotent | Fees | Auto | — | — | Yes | Pass |
| Auto-fines | Attendance unmarked skip; absent once | Fees | Auto | — | — | Yes | Pass |
| Meetings | Resolution vote action items | Meetings | Auto | — | Yes | Yes | Pass |
| Statements | Opening + movements reconcile | Member | Auto | — | Yes | Yes | Pass |
| Trends | Trailing avg forecast labelled | Reports | Auto | Browser 200 | — | Yes | Pass |
| Annual | Management summary keys + FY settings | Reports | Auto HTTP | Browser 200 | — | Yes | Pass |
| Settings | Save + empty name + member denied | Chair / Member | Auto + Browser | Browser save | Yes | Yes | Pass |
| Categories | Loan purposes CRUD tenant-scoped | Chair | Auto HTTP | Hub link | Yes | Yes | Pass |
| Documents | Active category required (no hard-coded list) | Docs | Code + create UI | — | Yes | Yes | Pass |
| Contributions | Payment methods = ContributionService list | Treasurer | UI aligned | — | Yes | Yes | Pass |
| Loans | Apply→approve→disburse→partial→settle + purpose | Integration | Auto | Create form | Yes | Yes | Pass |
| Platform | Admin audit 200 | Platform admin | Auto HTTP | — | Yes | N/A | Pass |
| Platform | Support audit 403 | Platform support | Auto HTTP | — | Yes | N/A | Pass |
| Platform | Chair/member audit 403 | Stokvel roles | Auto HTTP | — | Yes | N/A | Pass |
| Security | CSRF / SQLi / IDOR samples | Mixed | Auto | — | Yes | Yes | Pass |

## Browser / mobile (this pass)

| Area | Desktop click-through | Mobile viewport (390×844) | Status |
|---|---|---|---|
| Settings GET + Save settings flash | Pass | Pass (no page X-scroll) | Closed |
| Category hub + Loan purposes link | Pass | — | Closed |
| Loan create purpose select | Pass | Pass | Closed |
| Annual management summary | Pass (was 500 → fixed) | Pass | Closed |
| Members list | Pass | Cards; no page X-scroll | Closed |
| Core routes fetch 200 (logged-in) | Pass | — | Closed |
| Loan create + purpose catalogue | Pass | Pass | Closed (service lifecycle Pass; UI repay click-through P2) |

## Out of scope (do not test as blockers)

SMS, WhatsApp, payments, banks, DebiCheck, bureau, fraud, KYC, insurance, FSP, marketplace.
