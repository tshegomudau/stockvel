# Group Health scoring (internal)

Operational health from stokvel application data only.

**Not a credit rating, bureau score, or lending decision.**

Implementation: `App\Services\Analytics\GroupHealthService`.

## Overall score

Weighted average of **included** components (0–100), rounded:

```
score = round( Σ (component_score × weight) / Σ weights_of_included )
```

Components without enough data are **excluded** (`included = false`) and do not affect the denominator.

## Components

| Component | Weight | Formula | Skip when |
|---|---|---|---|
| Contribution collection | 30 | `paid / due × 100` on `member_charges` with `due_date` in the current UTC calendar month | No charges due this month |
| Meeting attendance | 20 | Present+late marks / all attendance rows on up to 6 completed meetings in the last 180 days | No completed meetings or no attendance rows |
| Profile completeness | 15 | Average of `ProfileCompletenessService` percent for active memberships | No active members |
| Arrears pressure | 20 | `100 − (outstanding / total_charged × 100)` on open contribution charges | Always included; score 100 if no charges |
| Loan repayment currency | 15 | `amount_paid / total_due × 100` on `loan_schedules` with `due_date ≤ today` | No loans exist |

## Bands

- 80–100 Strong
- 60–79 Steady
- 40–59 Needs attention
- 0–39 At risk

## Recommendations

Generated only from included components scoring below 70, using the same real counts shown in component detail. Never invented metrics.

## UI

- Full report: `/app/health` (`reports.view`)
- Dashboard card links to the report and states it is not a credit rating
