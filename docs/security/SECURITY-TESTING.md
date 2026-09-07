# Security Testing — SIS Phase 3.10

## Suites

| Suite | Path | Purpose |
|-------|------|---------|
| Security Feature | `tests/Feature/Security/` | API auth, headers, negative tests |
| Security Unit | `tests/Unit/Security/` | Policy, validator |
| Architecture | `tests/Architecture/` | Fitness including security_architecture |

## Run

```bash
php artisan security:test
php artisan test --testsuite=Security
```

## Negative Test Requirement (§53)

Every protected endpoint must have:
- Allowed request → success (with permission)
- Forbidden request → 401/403 (without auth/permission)

## Test Matrix

Machine-readable mapping: `.cursor/security/SECURITY-TEST-MATRIX.yaml`
