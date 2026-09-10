# Phase 3C.13 — Architecture Fitness

## Compliance checklist (design)

| Rule | Graduation write-path design |
|------|------------------------------|
| Clean Architecture layers | Command/Handler/Domain/Infra/Interface split |
| No business logic in controllers | Explicit thin controller rule |
| Domain framework-free | Guard already Illuminate-free; keep it |
| CQRS | Commands vs Queries separated |
| DI | Bind repos/handlers in ArchitectureServiceProvider |
| UnitOfWork owns txn | Mandatory |
| Outbox in txn | Mandatory |
| Idempotency on sensitive commands | Mandatory + fingerprint |
| academic_year_id + school_id | On academic ops |
| Tenant + RLS | GUC + FORCE; app not substitute |
| Fitness validators | Must pass on implementation: `architecture:validate --fitness`, `feature-check Graduation`, `architecture:graph` |
| application-feature skill | Scaffold via `sis:make-*` |
| ADR-013 / ADR-014 | Reuse stores; extend fingerprint semantics for Graduation |

## Exceptions

None proposed.  
Do **not** request fitness exemptions for Graduation.

## Documented debt (outside Graduation implementation)

```text
TECHNICAL DEBT: Enrollment/Exams idempotency store after commit (no fingerprint assert)
```

Graduation must not inherit this debt.

## Database-first protection

```text
Application guards are NOT the final authority.
```

DB retains UNIQUE, FK, partial current indexes, immutability triggers, reject-delete, RLS/FORCE.

## Verdict

```text
ARCHITECTURE FITNESS: PASS
```
