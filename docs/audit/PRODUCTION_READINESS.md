# Production readiness (interim)

**Verdict:** Not ready for unsupervised production promotion. Critical webhook and several high tenancy/finance defects are fixed on branch `audit/production-2026-11-10`, but dual-control funds, CI, CodeRabbit review, and full UI/E2E matrices remain open.

## Acceptance checklist (from audit brief)

| Criterion | Status |
|-----------|--------|
| Critical vulnerabilities resolved | **Webhook Critical fixed**; re-verify on staging |
| High defects resolved or approved exceptions | Partially — funds dual-control still open |
| Financial integrity tests pass | Suite green; repay idempotency still a gap |
| Tenant isolation tests pass | Existing + suspended membership fix |
| Authn/authz tests pass | Pass |
| Registration/onboarding | Covered partially; expand Phase 4 |
| Platform administration | Smoke covered; not full click-through |
| Email notifications | Log-driver tests only |
| Frontend / E2E browser | Not fully executed this phase |
| Migrations validated on sanitized prod copy | **Not done** — requires approved dump |
| CodeRabbit triaged | **Blocked** on CLI auth |
| No critical regressions in suite | **131/131 pass** |
| Production data preservation | No prod writes performed |
| Deploy/rollback plan documented | See below — **do not auto-deploy** |

## Deploy / rollback (when approved)

1. Merge only after CodeRabbit + human review.
2. Backup production DB before migrate.
3. Deploy code (shared hosting zip / `deploy_stokvel.sh` pattern) **without** dropping tables.
4. Run `php bin/console migrate` — this branch introduces **no new migrations**.
5. Smoke: login, platform audit, contribution capture, loan approve as non-applicant, webhook 401 without signature.
6. Rollback: restore previous release artifact; DB rollback not required for these code-only fixes.

## Explicit non-actions this audit

- No production DB access
- No live payments/emails
- No force-push / secret changes
- No framework upgrades
