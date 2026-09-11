# Laravel Application Architecture

> **STATUS: LEGACY / NON-AUTHORITATIVE — historical reference only**  
> **Do NOT use this document for new features.**

## Authoritative architecture (mandatory)

```text
00-SIS-CONSTITUTION → 01-ARCHITECTURE → ARCHITECTURE-STACK.md
→ clean-architecture.mdc → architecture-governance.mdc
→ Application Handlers (Commands/Queries) → Domain → Infrastructure
```

| Need | Read |
|------|------|
| Layer rules | [ARCHITECTURE-STACK.md](./ARCHITECTURE-STACK.md) |
| Feature workflow | `.cursor/skills/application-feature/SKILL.md` |
| Enforcement | `php artisan architecture:validate --fitness` |
| Infrastructure Laravel glue | `.cursor/rules/laravel-patterns.mdc` (subordinate) |

---

## Why this file exists

This document preserves **pre–Clean Architecture** Laravel starter guidance from early project bootstrap. It is retained for historical context only.

**It must NOT be interpreted as permission to:**

- create `App\Services\*` classes for domain workflows
- place business logic in Eloquent models or controllers
- default to Controller → Service → Model CRUD

---

## Current SIS application pattern (authoritative)

```text
HTTP Controller (thin)
    → authorize + FormRequest
    → Application Command/Query Handler
    → Domain (pure PHP)
    → Infrastructure (Eloquent *Record, repositories, jobs)
```

| Layer | Location | Role |
|-------|----------|------|
| Domain | `app/Domain/{Context}/` | Business rules, entities, VOs |
| Application | `app/Application/{Context}/Commands|Queries/` | Handlers, DTOs, Results |
| Infrastructure | `app/Infrastructure/` | Persistence, queues, external IO |
| HTTP | `app/Http/` | Controllers, middleware, requests |

---

## Legacy exceptions (explicitly permitted)

| Artifact | Status | Notes |
|----------|--------|-------|
| `app/Services/Attendance/AttendanceBatchService.php` | **R1.9 Option B QUARANTINED** | Runtime fail-closed; CQRS is authoritative; full deletion needs separate authorization |
| Pre-Clean Architecture docs below | Historical | Superseded by ARCHITECTURE-STACK |

Do **not** add new legacy Service classes without architecture review.

---

## Historical content (superseded — do not follow)

<details>
<summary>Legacy layer diagram and Service pattern (archived)</summary>

The sections below described a Service-first Laravel layout. **Superseded** by handler-first Clean Architecture.

```
HTTP → Services + Actions → Domain Models → Infrastructure
```

See git history before Phase 0.2/Phase 1 for full legacy text if needed.

</details>

---

## Related (authoritative)

- [ARCHITECTURE-STACK.md](./ARCHITECTURE-STACK.md)
- [api-conventions.md](./api-conventions.md)
- [normalization-and-cqrs.md](./normalization-and-cqrs.md)
- `.cursor/rules/laravel-patterns.mdc`
