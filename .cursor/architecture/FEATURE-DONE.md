# Feature Definition of Done (SIS)

> A feature is **not complete** until required items pass.  
> **Source of truth:** `architecture:validate` + Architecture tests + CI — not Cursor alone.

## Enforcement Stack

```text
ARCHITECTURE-STACK.md  →  Cursor Rules + Skills  →  Artisan Scaffolding
        →  architecture:validate  →  Architecture Tests  →  CI / Git
```

| Role | Tool |
|------|------|
| Assistant | Cursor Rules + `application-feature` skill |
| Scaffold | `sis:make-feature`, `sis:make-command`, `sis:make-query` |
| Inspector | `architecture:validate --fitness`, `architecture:graph` |
| Proof | `tests/Architecture/*` |
| Gate | CI (`composer test`) |

---

## Before Creating Any Feature

**Do NOT** scaffold Laravel-style `app/Models/X` + fat Controller for new business features.

Use:

```bash
php artisan sis:make-feature {Context} --command={WriteUseCase} --query={ReadUseCase}
```

Then implement layers bottom-up: Domain → Application → Infrastructure → Http.

---

## Checklist (mark Required / Optional / N/A per feature)

| # | Item | Default | Notes |
|---|------|---------|-------|
| 1 | Bounded context identified | **Required** | e.g. `Enrollment`, `Teacher` |
| 2 | Domain Entity with behavior | **Required** | Not anemic Eloquent model |
| 3 | Value Objects for invariants | Optional | When IDs/codes/money need rules |
| 4 | Specification for complex rules | Optional | Eligibility, academic rules |
| 5 | Command + Handler + Result | **Required** for writes | CQRS write side |
| 6 | Query + Handler + DTO | **Required** for reads | CQRS read side |
| 7 | Repository port + adapter | Optional | When persistence is non-trivial |
| 8 | UnitOfWork transaction | **Required** for writes | Handler owns boundary |
| 9 | Domain event + Outbox | **Required** when side effects | Stage inside transaction |
| 10 | Idempotency key | **Required** for sensitive ops | Enrollment, payments, imports |
| 11 | Form Request + Policy | **Required** for HTTP | Authorization + validation |
| 12 | `school_id` + `academic_year_id` | **Required** if academic | RLS + tenant scope |
| 13 | Unit tests (Domain + Handler) | **Required** | Mock ports |
| 14 | Feature test (HTTP) | Optional | When endpoint exists |
| 15 | `architecture:validate --fitness` | **Required** | Must PASS |
| 16 | Performance review | Optional | Heavy reads / reports |

---

## Fitness Categories (must PASS in CI)

| Category | Validates |
|----------|-----------|
| `domain_purity` | No Laravel/Application/Infrastructure in Domain |
| `dependency_direction` | Presentation → Application → Domain ← Infrastructure |
| `application_isolation` | No Http/Eloquent/DB in Application |
| `controller_thinness` | No DB/Eloquent in controllers |
| `handler_rules` | Handlers use UnitOfWork + ports |
| `intelligence_alignment` | Intelligence UI uses Application handlers |

Run: `php artisan architecture:validate --fitness`  
Run: `php artisan architecture:feature-check {Context}`  
Baseline: `.cursor/architecture/ARCHITECTURE-BASELINE.json`

---

## Intelligence & Self-Learning Features

Same stack as business features. Additional gates:

```text
Recommendation → Risk Engine → Governance Policy → Allowed?
                                    ↓ NO          ↓ YES
                               Human Approval    Execute → Verify → Rollback
```

- **Never** auto-execute schema destruction (DROP, ALTER without approval).
- Learning updates **confidence**, not permissions — see `SELF-HEALING-RUNBOOK.md`.

---

## Prompt Guidance for Developers

Short functional prompt is enough:

> "أنشئ Feature تسجيل معلم"

Cursor reads Rules + Skill automatically. If output violates architecture, CI fails — fix before merge.

Do **not** rely on Cursor as the only enforcer.
