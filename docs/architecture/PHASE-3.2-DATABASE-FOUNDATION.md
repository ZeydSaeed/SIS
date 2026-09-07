# Phase 3.2 — Database Foundation

**Phase:** 3.2  
**Date:** 2026-09-07  
**Previous:** Phase 3.1 Application Foundation  
**Status:** Complete — ready for Phase 3.3 (Students vertical slice)

---

## Executive Summary

Phase 3.2 verifies the **existing PostgreSQL migration stack** (20 migrations, 63 tables on local Herd) and adds **minimal idempotent foundation seeds** for organization + academic reference data required by the Students vertical slice. No new migrations — schema already matches Phase A design.

---

## Verified State (Local PostgreSQL)

| Metric | Value |
|--------|-------|
| Driver | `pgsql` |
| Database | `sis` |
| Migrations | **20/20 applied** |
| Business tables | **63** (24 schemas) |
| Blueprint target | 89 tables (remaining modules not yet migrated) |

Key schemas present: `organization`, `academic`, `students`, `enrollment`, `intelligence`, `attendance`, …

---

## Deliverables

| Item | Location | Purpose |
|------|----------|---------|
| Foundation reference codes | `database/seeders/Support/FoundationReference.php` | Stable codes for tests/API |
| Organization seed | `FoundationOrganizationSeeder` | Ministry → School → Branch → Department |
| Academic seed | `FoundationAcademicSeeder` | Current year, terms, grade levels |
| Orchestrator | `SisFoundationSeeder` | Calls both seeders |
| Verifier service | `App\Database\DatabaseFoundationVerifier` | Programmatic checks |
| Artisan command | `php artisan sis:verify-database` | CLI verification + optional `--seed` |
| Tests | `FoundationSeederTest`, verifier tests, PG verification | SQLite + PG coverage |
| DatabaseSeeder | Updated | Runs foundation seed + test user |

---

## Foundation Seed Data

| Entity | Code | Notes |
|--------|------|-------|
| Ministry | `MOE` | Ministry of Education |
| Directorate | `DIR-DEMO` | Demo region |
| School | `SCH-DEMO` | Demo Vocational School |
| Branch | `BR-MAIN` | Main campus |
| Department | `DEP-VOC` | Vocational department |
| Academic Year | `2026-2027` | `is_current = true` |
| Terms | `T1`, `T2` | Two terms |
| Grade Levels | `G10`, `G11`, `G12` | Vocational stage reference |

Seeders are **idempotent** — safe to re-run via `db:seed`.

---

## Commands

```bash
# Apply all migrations (if needed)
php artisan migrate

# Seed foundation reference data only
php artisan db:seed --class=SisFoundationSeeder

# Full dev seed (foundation + test user)
php artisan db:seed

# Verify migrations, schemas, tables, and foundation seed
php artisan sis:verify-database

# Seed then verify in one step
php artisan sis:verify-database --seed

# CI/SQLite: skip foundation seed check
php artisan sis:verify-database --no-seed-check
```

---

## Verification Checks

`sis:verify-database` runs:

1. **database_driver** — warns if not PostgreSQL
2. **migrations** — no pending migrations
3. **table:\*** — 9 required tables for Students slice
4. **schema:\*** — all 24 SIS schemas (PostgreSQL only)
5. **table_count** — minimum 50 business tables across SIS schemas (PostgreSQL only)
6. **foundation_seed** — `SCH-DEMO` + `2026-2027` current year

---

## Test Matrix

| Test | Driver | Purpose |
|------|--------|---------|
| `FoundationSeederTest` | SQLite (in-memory) | Seed data + idempotency |
| `DatabaseFoundationVerifierTest` | SQLite | Verifier logic |
| `PostgreSqlFoundationVerificationTest` | PostgreSQL | Full foundation on real PG |

Run:

```bash
php artisan test --filter=Foundation
php artisan test --filter=DatabaseFoundationVerifier
php artisan test -c phpunit.optimization-pgsql.xml --filter=PostgreSqlFoundation
```

---

## SQLite vs PostgreSQL

| Concern | SQLite (PHPUnit default) | PostgreSQL (local dev) |
|---------|--------------------------|------------------------|
| Schema names | Prefixed tables (`organization_schools`) | Native schemas |
| Migrations | Same files via `SchemaHelper` | Same files |
| Foundation seed | ✅ Works | ✅ Works |
| Schema verification | Skipped | Full 24-schema check |
| RLS / pg_stat | Skipped | Enabled via migrations |

---

## What Is NOT in Phase 3.2

- New migrations or blueprint changes (no schema delta)
- 45K seed dataset (`seed-data-45k.md` — Phase 3.6+)
- Student CRUD (Phase 3.3)
- Eloquent models for organization/academic (Infrastructure records deferred to feature need)

---

## Next: Phase 3.3 — Students Vertical Slice

Prerequisites now satisfied:

- ✅ PostgreSQL migrations applied
- ✅ Foundation school + academic year seeded
- ✅ Grade levels reference data
- ✅ `students.students` table exists
- ✅ API foundation from Phase 3.1

Implement: `CreateStudent`, `UpdateStudent`, `ListStudents`, `SearchStudents` + `/api/v1/students` endpoints.

---

*Phase 3.2 complete. Run `php artisan sis:verify-database --seed` before starting Phase 3.3.*
