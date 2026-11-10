# Test results

## Baseline (pre-fix)

```
php bin/console test --env=testing
126 passed, 0 failed, 0 blocked.
```

Database: `stockvel_testing` only.

## After Phase 3 fixes (2026-11-10)

```
php bin/console test --env=testing
132 passed, 0 failed, 0 blocked.
```

New cases include webhook signature rejection/idempotency, HTTP 401 without signature, contribution idempotency, suspended membership denial, loan self-approve assertion, and same-stokvel peer support-ticket isolation.

## Dependency audit

```
composer audit
No security vulnerability advisories found.
```

## Not executed yet

- CodeRabbit CLI review (auth blocked in agent sandbox)
- Browser E2E against a running server
- PHPStan (not configured)
- Production DB migration dry-run (requires approved sanitized dump — not available here)
