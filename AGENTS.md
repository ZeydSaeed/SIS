# AGENTS.md — SIS Project Guide for AI Agents

## Quick Start

This is an **Enterprise Student Information System (SIS)** built with Laravel 13 + Inertia/React + PostgreSQL.

**Current phase:** Architecture reference defined. Database blueprint exists but migrations are NOT yet implemented.

## Read First

| Priority | File | Purpose |
|----------|------|---------|
| 1 | `.cursor/brain/PROJECT.md` | Project identity and core rules |
| 2 | `.cursor/architecture/README.md` | Architecture index |
| 3 | `.cursor/architecture/database-blueprint.md` | 85-table reference blueprint |
| 4 | `.cursor/brain/student-lifecycle.md` | Domain flow |
| 5 | `.cursor/architecture/improvement-matrix.md` | What to build and when |

## Key Constraints

- **Do NOT** hard-delete academic records
- **Do NOT** run heavy operations synchronously in HTTP requests
- **Always** scope academic operations to `academic_year_id`
- **Always** use versioned migrations for any DB changes

## Database Changes — MANDATORY WORKFLOW

Every create/alter/index/delete/improvement to the database **must** follow:

```
1. Read  .cursor/skills/database-change/SKILL.md
2. Read  .cursor/architecture/database-blueprint.md
3. Fill  .cursor/architecture/DATABASE-CHANGE-CHECKLIST.md
4. Write migration + model
5. Update database-blueprint.md (always)
6. Update indexing-matrix.md (if indexes changed)
7. Verify migrate up + rollback
```

**Never** skip reference files. **Never** change schema without updating blueprint.

## Architecture Layers

```
Layer 1: Data Integrity (normalization, FK, constraints, audit)
Layer 2: Performance (indexes, partitioning, query optimization)
Layer 3: Scalability (Redis, queues, materialized views, replicas)
Layer 4: Resilience (backup, PITR, DR, monitoring)
```

## Domain Modules (implement in order)

```
organization → academic → security → students → enrollment
→ curriculum → teachers → timetable → attendance → exams
→ results → promotion → transfers → graduation → certificates
→ finance → communication → audit → reports
```

## Cursor Rules

Active rules in `.cursor/rules/`:
- `sis-core.mdc` — always applied
- `database-changes-mandatory.mdc` — **mandatory** for `database/**/*` and `app/Models/**/*`
- `database-design.mdc` — for `database/**/*`
- `laravel-patterns.mdc` — for `app/**/*.php`
- `query-optimization.mdc` — for `app/**/*.php`

## Skills

- `.cursor/skills/database-change/SKILL.md` — **must read** before any database work

## When Implementing a Feature

1. **Database change?** → Follow mandatory workflow in `.cursor/skills/database-change/SKILL.md`
2. Check blueprint table in `database-blueprint.md`
3. Complete `DATABASE-CHANGE-CHECKLIST.md`
4. Create migration following `database-design.mdc`
5. Create model in domain namespace
6. **Update `database-blueprint.md`** after schema change
7. Create service + policy
8. Create controller + Inertia page
9. Add audit logging for data changes
10. Queue any operation > 100 records

## Tech Stack

- Backend: Laravel 13, PHP 8.3, Fortify + Passkeys + 2FA
- Frontend: React 19, Inertia 3, Tailwind 4, Radix UI
- Target DB: PostgreSQL (schemas per domain)
- Cache/Queue: Redis
- Files: Object storage (metadata in DB)
