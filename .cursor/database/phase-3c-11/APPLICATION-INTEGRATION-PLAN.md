# PHASE 3C.11 — APPLICATION INTEGRATION PLAN

**Application code NOT modified.**

## Context placement

Propose bounded context name: **`Graduation`** (maps to schema `graduation`; includes Completion + Approval + Award — namespaced commands prevent collapse).

Scaffold later via:

```text
php artisan sis:make-feature Graduation
php artisan sis:make-command Graduation <Name>
php artisan sis:make-query Graduation <Name>
```

Follow `application-feature` skill + ARCHITECTURE-STACK. Mirror Exams patterns.

---

## Layer map

| Layer | Future paths (planned) |
|-------|------------------------|
| Domain | `app/Domain/Graduation/...` entities, VOs, exceptions, repository interfaces, domain events, write guards |
| Application | `app/Application/Graduation/Commands|Queries|DTOs|Results|Contracts` |
| Infrastructure | `app/Infrastructure/Persistence/Graduation/...` Eloquent adapters |
| Presentation | Controllers/FormRequests/Policies when API wave authorized |
| Shared | Reuse UnitOfWork, OutboxRepository, IdempotencyStore, SchoolContext |

**DO NOT TOUCH** Domain Exams grades SSOT; enrollment SSOT; audit table schemas.

---

## Operations map

| Operation | Type | Authz | Idempotency | Outbox | Policy dependency |
|-----------|------|-------|-------------|--------|-------------------|
| Upsert draft policy/version | Command | school admin TBD | optional | optional | HD-20 content |
| Publish policy version | Command | publish authority TBD | YES | optional | roles TBD |
| Evaluate completion (candidate) | Command | system/registrar TBD | YES | completion.evaluated | Engine needs policy **content** — POLICY DEPENDENCY |
| Publish official completion | Command | TBD | YES | eligibility / recorded | HD-35 |
| Record evidence (with eval) | part of evaluate | — | — | — | — |
| Request approval | Command | TBD | YES | approval_requested | HD-31 roles |
| Decide approval | Command | human approver TBD | YES | approval_decided | HD-31 — POLICY DEPENDENCY |
| Issue award | Command | after approved | YES | award_issued | HD-32 attrs optional |
| Supersede completion/award | Command | TBD | YES | outcome_superseded | HD-35 |
| Revoke award | Command | authority TBD | YES | award_revoked | HD-36 reasons TBD |
| Get current official state | Query | school | — | — | — |
| Reconstruct historical state | Query | elevated/audit | — | — | — |
| Rebuild StudentStatus | Command (ops) | elevated | YES | — | multi-enrollment policy |

If operation needs unresolved policy:

```text
POLICY DEPENDENCY — DO NOT IMPLEMENT
```

---

## Example command anatomy (future)

```text
PublishOfficialCompletionCommand
  → PublishOfficialCompletionHandler
      UnitOfWork::transaction
        IdempotencyStore remember/replay
        Domain guard + repository writes
        OutboxRepository::stage
      → PublishOfficialCompletionResult
```

Authorization: Policy class + server-side check. academic_year_id + school_id on academic ops.

## Fitness gates (must remain)

```text
php artisan architecture:validate --fitness
php artisan architecture:feature-check Graduation
php artisan architecture:graph
php artisan security:validate   # when available
```

Do not weaken gates for Graduation.
