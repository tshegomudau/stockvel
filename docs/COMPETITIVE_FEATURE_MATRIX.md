# Competitive Feature Matrix (internal)

Public competitor capability descriptions used only as product inspiration.  
No competitor branding, IP, or proprietary designs are copied.

Statuses: ✅ Implemented · 🟡 Partial · ⚪ Future · 🔴 Blocked · ➖ N/A · 🔌 Out of phase

| Feature | Existing Status | Required Work | External Dependency | Test Status |
|---|---|---|---|---|
| Member management | ✅ Implemented | — | None | Covered |
| Multi-stokvel switcher | ✅ Implemented | — | None | Partial |
| Invitations + admin approval | ✅ Implemented | — | None | Covered |
| Bulk member import | ✅ Implemented | Harden preview txn | None | Partial |
| Beneficiaries | 🟡 Partial | Report download polish | None | Partial |
| Contributions capture/import | ✅ Implemented | — | None | Covered |
| Contribution obligations/schedules | 🟡 Partial | Member schedule UI polish | None | Partial |
| Contribution dashboard KPIs | ✅ Implemented | — | None | Manual |
| Joining fees | ✅ Implemented | Anniversary charge job optional | None | Partial |
| Admin Pocket / Funds | ✅ Implemented | — | None | Fund reconciliation |
| Member statements (CSV/print/ranges) | ✅ Implemented | Dedicated PDF engine optional | None | Statement balance |
| Group financial reports | ✅ Implemented | — | None | Partial |
| Arrears management (ageing) | ✅ Implemented | In-app reminder polish | None | Partial |
| Constitution | ✅ Implemented | — | None | Partial |
| Rules | ✅ Implemented | Cross-link constitution | None | Covered |
| Meetings + attendance | ✅ Implemented | — | None | Meeting workflow |
| Resolutions / votes / action items | ✅ Implemented | Quorum UI polish | None | Meeting workflow |
| Fines / fine types / bulk | ✅ Implemented | — | None | Fine workflow |
| Auto-fines (preview + idempotent) | ✅ Implemented | — | None | AutoFineTrendsTest |
| Loans lifecycle | 🟡 Partial | Threshold policy UX | None | Covered core |
| Approvals + multi-approval | ✅ Implemented | — | None | Multi-approver |
| Announcements | ✅ Implemented | — | None | Partial |
| In-app notifications | ✅ Implemented | Preferences UI | None | Partial |
| Email notifications | 🟡 Partial | Template polish | SMTP optional | Partial |
| SMS / WhatsApp | ➖ Out of phase | — | Provider | N/A |
| Executive positions / handover | 🟡 Partial | Handover records UI | None | Missing |
| Stokvel profile/settings | 🟡 Partial | Logo upload polish | None | Partial |
| Configurable categories | ✅ Implemented | Ongoing hard-code sweep | None | Category tests |
| Group health | ✅ Implemented | — | None | Unit |
| Saving goals | ✅ Implemented | — | None | Unit |
| Knowledge base | ⚪ FUTURE ENHANCEMENT | Not in nav — optional | None | — |
| Activity feed | 🟡 Partial | Dedicated feed page (not audit log) | None | — |
| Audit log (platform only) | ✅ Implemented | `audit_logs.view` required | None | AuditLogAccessTest |
| Profile completeness | ✅ Implemented | — | None | Unit |
| Interest allocation (accounting) | ⚪ FUTURE ENHANCEMENT | Deferred — not phase-required | None | — |
| Dashboard (role-aware) | ✅ Implemented | Member portal cards polish | None | Partial |
| Trends / forecasting | ✅ Implemented | Trailing-average labelled forecasts | None | AutoFineTrendsTest |
| Annual summary | ✅ Implemented | Print-friendly management summary | None | AutoFineTrendsTest |
| Support tickets | ✅ Implemented | — | None | Covered |
| PWA | ⚪ FUTURE ENHANCEMENT | No installable PWA; mobile UX mandatory | None | — |
| Executive handover | ⚪ FUTURE ENHANCEMENT | After P0/P1 closed | None | — |
| SMS / WhatsApp | ➖ OUT OF SCOPE — FUTURE PHASE | Not a readiness blocker | Provider | N/A |
| Banks / DebiCheck / insurance / bureau / marketplace | ➖ OUT OF SCOPE — FUTURE PHASE | Not a readiness blocker | Regulated | N/A |

## Phase rule

This phase builds a self-contained stokvel management platform. External/regulated financial services are excluded from navigation and must not show faux balances, scores, or “coming soon” CTAs.
