# Phase 3C.16 — Test Strategy

## Unit

- `EvaluatorApproverSeparationTest`
- `CreateCompletionOutcomeHandlerTest` (Case A/B + in-txn store)
- Existing `GraduationIdempotencyGuardTest`

## PostgreSQL (`phpunit.database-pgsql.xml` → sis_test)

`GraduationWritePathPostgreSqlTest`:

- Idempotent create + UNIQUE Case C
- SoD evaluator cannot approve
- Approve → Issue → Revoke + Publish gated
- Multi-enrollment independence + student status untouched
- Cross-school SchoolContext rejection

## Regression (required)

- Phase3C12GraduationSchemaTest
- Existing Graduation concurrency/idempotency PG tests
- architecture:validate --fitness
- architecture:feature-check Graduation (as available)
