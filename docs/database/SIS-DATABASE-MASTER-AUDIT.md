# SIS Database Master Audit

**Phase:** 0 — Discovery (READ-ONLY)  
**Date:** 2026-09-10  
**Database:** `sis` @ PostgreSQL 18.2  
**Status:** COMPLETE — no schema changes performed  
**Sources:** live PostgreSQL catalog, Laravel migrations, `.cursor/architecture/database-blueprint.md`, master ERP prompt, `AGENTS.md`, Phase 3.2 foundation docs

---

## A. Current State

### Connection

| Item | Value |
|------|-------|
| Engine | PostgreSQL **18.2** (x86_64-windows, MSVC) |
| Database name | `sis` |
| Connected user | `postgres` |
| Laravel driver | `pgsql` (`.env`) |
| Host / port | `127.0.0.1:5432` |
| Migrations applied | **24** (batches 1–5) |
| `sis:verify-database` | **PASS** (36/36) — 54 business tables counted |

### Extensions

| Extension | Version | Purpose |
|-----------|---------|---------|
| `plpgsql` | 1.0 | Default procedural language |
| `pg_stat_statements` | 1.12 | Query telemetry for intelligence layer |

Not installed (evaluated, not required yet): `pgcrypto`, `citext`, `uuid-ossp`, `pg_trgm`.

### Schemas (25)

Present in catalog:

```text
academic, attendance, audit, certificates, communication, curriculum,
documents, enrollment, exams, finance, graduation, guardians, intelligence,
organization, promotion, public, reports, results, security, students,
teachers, timetable, transfers, vocational, workflow
```

**Note:** Empty schemas exist for future modules (`exams`, `finance`, `certificates`, …).  
**Gap:** Blueprint schema `admission` is **not** created by `SchemaHelper::schemas()` and does not exist in the live catalog.

### Relations inventory (live)

| Kind | Count (approx.) |
|------|-----------------|
| Ordinary tables | ~55 domain + framework |
| Partitioned parent | 1 (`attendance.records`) |
| Partition children | 1 (`attendance.records_default`) |
| Materialized views | 3 |
| Views | 2 (`pg_stat_statements*`) |
| **Total relations discovered** | **70** |

### Tables by schema (implemented)

| Schema | Objects |
|--------|---------|
| `organization` | ministries, directorates, schools, branches, departments, rooms |
| `academic` | academic_years, terms, grade_levels, holidays, system_settings |
| `vocational` | specializations, tracks, specialization_subjects |
| `students` | students, student_contacts, student_addresses |
| `guardians` | guardians, student_guardians, guardian_addresses |
| `enrollment` | classes, sections, enrollments, enrollment_subjects |
| `teachers` | teachers, teacher_schools, teacher_subjects, teacher_qualifications |
| `curriculum` | subjects, curricula, curriculum_subjects |
| `timetable` | periods |
| `attendance` | sessions, records (partitioned), records_default, daily_section_summary |
| `security` | roles, permissions, role_permissions, user_roles, scopes, security_audit_logs |
| `audit` | outbox_messages, idempotency_keys |
| `reports` | mv_school_student_statistics, mv_daily_attendance, mv_directorate_school_comparison |
| `intelligence` | monitoring_snapshots, table_metrics, query_metrics, detections, recommendations, optimization_events, baseline_snapshots, human_feedback_events, self_healing_actions, optimization_validation_target |
| `public` | users, sessions, password_reset_tokens, cache, cache_locks, jobs, job_batches, failed_jobs, passkeys, personal_access_tokens, migrations |

Empty schemas (no tables): `admission` (missing), `exams`, `results`, `promotion`, `transfers`, `graduation`, `certificates`, `documents`, `finance`, `communication`, `workflow`.

### Primary keys

| Pattern | Where used |
|---------|------------|
| `BIGINT GENERATED … IDENTITY` via `$table->id()` | Most OLTP tables |
| `SMALLINT` serial (`smallIncrements`) | `grade_levels`, `roles`, `permissions`, `periods` |
| Composite PK | `attendance.daily_section_summary (section_id, attendance_date)`; `security.role_permissions`; partitioned `attendance.records (id, academic_year_id)` |
| External UUID | `students.students.public_id` (unique, nullable) — **not** internal PK |

**Documented project rule:** internal PKs remain **BIGINT IDENTITY**, not UUID. Master prompt preference for UUIDv7 is a **decision conflict** (see §E).

### Foreign keys

| Metric | Value |
|--------|-------|
| FK constraints observed | 54+ (catalog enumeration; partition inheritance inflates counts) |
| ON DELETE RESTRICT | Dominant (~50) — correct for academic integrity |
| ON DELETE CASCADE | 2 (non-academic: passkeys→users; intelligence feedback→recommendations) |
| ON DELETE SET NULL | 2 (nullable operational FKs) |

### Unique / check constraints / indexes

| Object | Status |
|--------|--------|
| Unique constraints | Present on business codes, national IDs, enrollment uniqueness patterns |
| CHECK constraints | **None** found in live catalog |
| Indexes | **183** (includes PK/unique/partition indexes) |
| EXCLUDE constraints | **None** |

### RLS / policies

| Table | RLS enabled | FORCE RLS | Policy |
|-------|-------------|-----------|--------|
| `enrollment.enrollments` | YES | NO | `enrollment_school_isolation` (ALL) — fail-closed on `app.current_school_id` |
| `attendance.records` | YES | NO | `attendance_school_isolation` (ALL) — fail-closed |
| All other school-scoped tables | NO | NO | — |

### Views / materialized views / triggers / functions / sequences / partitions

| Object type | Status |
|-------------|--------|
| Materialized views | 3 in `reports` |
| Application triggers | **0** |
| Functions | Only `pg_stat_statements*` in `public` |
| Sequences | Identity/serial sequences for tables |
| Partitions | `attendance.records` **LIST (`academic_year_id`)** + `records_default` |

---

## B. Existing SIS Coverage

| Domain | DB coverage | App feature coverage | Notes |
|--------|-------------|----------------------|-------|
| Schools / org hierarchy | ✅ Full (6 tables) | Seed/verify only | `branches` ≈ campuses |
| Academic years / terms | ✅ Full | Seed only | |
| Students | ✅ Core (3/4) | ✅ Clean Architecture slice | Missing `student_documents` |
| Guardians | ✅ Full (3) | Partial | |
| Enrollment | ✅ Full (4) | ✅ Clean Architecture | RLS on |
| Teachers | ✅ Full (4) | Schema only | Not generalized staff/HR |
| Curriculum / vocational | ✅ Mostly | Schema only | Missing `prerequisites` |
| Attendance | ✅ Core + partition + summary | Legacy batch service | Period + session model |
| Timetable | ⚠ Partial | — | Only `periods`; no schedules |
| Security / RBAC | ⚠ Partial | Middleware + policies | `public.users` not `security.users` |
| Audit | ⚠ Partial | Outbox + idempotency | Missing `audit_logs`, `login_history` |
| Intelligence | ✅ 9+1 tables | ✅ Runtime platform | Outside academic 89 count |
| Exams / grades | ❌ Empty schema | — | Critical gap |
| Finance | ❌ Empty schema | — | Fee-only blueprint not migrated |
| Documents / communication / workflow | ❌ Empty | — | |
| Lifecycle (admission, promotion, transfer, graduation, certificates) | ❌ Empty / missing admission schema | — | |
| Reporting MVs | ⚠ 3 of 8 | — | Column drift vs blueprint |

---

## C. Missing Domains

### C1. Blueprint domains not migrated (~40+ objects)

Admission, exams, results, promotion, transfers, graduation, certificates, documents, finance, communication, workflow; plus partials (`student_documents`, `prerequisites`, `timetable.schedules/exceptions`, `audit_logs`, remaining MVs).

### C2. Master ERP prompt domains beyond current blueprint

These are **not** in `database-blueprint.md` today and must be treated as **future target expansion** (not Phase 1 defaults):

| Domain | Example concepts | Priority suggestion |
|--------|------------------|---------------------|
| Identity SSOT | `persons` unifying student/employee/guardian | Architecture decision before Phase 2 |
| Workshops / labs | capacity, safety_capacity, equipment, section_batches | High (vocational core) |
| Block scheduling | duration_periods, conflict ranges | High with timetable |
| HR / payroll | employees, contracts, leaves, payroll runs | Medium — ERP track |
| Inventory / assets | warehouses, stock, workshop materials | Medium |
| Facilities / maintenance | requests, costs | Medium |
| Transportation | buses, routes, subscriptions | Medium |
| Medical / health | allergies, clinic visits (HIGHLY_RESTRICTED) | High security, phased |
| SEN / IEP | iep_plans, accommodations | High security, phased |
| Behavior / discipline | incidents, merits/demerits | Medium |
| Activities / assemblies / duties | clubs, assemblies, duty_rosters | Medium |
| Internship / summer training | partners, placements, logbooks | High for vocational |
| Alumni / library / volunteering | alumni, loans, volunteer_logs | Later |
| Full GL finance | chart_of_accounts, journals | Later — expand beyond fee tables |
| Reference / archive schemas | shared enums, cold archive | As needed |

**Principle:** Do not create empty schemas for every name in the master list. Each schema needs a documented responsibility and an approved phase.

---

## D. Duplicates

| Duplicate / overlap | Resolution direction |
|---------------------|----------------------|
| Blueprint `security.users` vs Laravel `public.users` | Keep `public.users` as auth identity; document blueprint deviation or add thin `security` profile mapping later |
| Blueprint `security.sessions` vs Laravel `sessions` | Keep Laravel session store; do not create parallel token table without ADR |
| Teachers vs future Staff/Employees | Teachers remain academic assignment; HR `employees` should reference person, not duplicate teacher rows |
| `organization.rooms` vs future facilities/workshops | Rooms stay physical locations; workshops may specialize room_type or link 1:1 |
| `security.security_audit_logs` vs future `audit.audit_logs` | Distinguish security events vs domain change audit |
| Blueprint table count 86 vs 89 vs AGENTS “89” | Doc debt — reconcile in Phase 1 architecture docs |
| Duplicate migration adding `students.school_id` | Already applied; leave history; no rewrite |

---

## E. Conflicts

| Conflict | Detail | Risk |
|----------|--------|------|
| PK strategy | Project constitution/blueprint: **BIGINT IDENTITY**. Master prompt: prefer UUID/UUIDv7. | HIGH if mixed mid-flight |
| Schema naming | Master prompt lists `core`, `identity`, `student` (singular). Live schemas use `organization`, `students`, etc. | MEDIUM — preserve live names |
| Campus naming | Prompt: campuses. Live: `organization.branches` | LOW — semantic alias |
| Person model | Prompt: single Person SSOT. Live: separate students/guardians/teachers/users | HIGH design decision |
| Soft delete | Prompt allows temporal fields; Laravel SoftDeletes unused — aligned | OK |
| RLS scope | Prompt: all `school_id` tables. Live: 2 tables only; `FORCE ROW LEVEL SECURITY` off | HIGH security gap |
| CHECK constraints | Prompt requires DB constraints; live has **zero** CHECKs | MEDIUM integrity gap |
| Admission schema | In blueprint, missing from `SchemaHelper` | MEDIUM |
| Intelligence autonomy | Prompt forbids destructive auto-DDL; existing safety model Tier 1 only — aligned | OK if preserved |
| Table count SSOT | 86 header / 89 footer / 87 headings | LOW doc |

---

## F. Migration Risks

| Risk | Impact | Mitigation |
|------|--------|------------|
| Rewriting early migrations | Breaks shared/local DBs already migrated | **Forbidden** — additive migrations only |
| Adding RLS without app context | Fail-closed policies can break jobs/seeders | Set `app.current_school_id` in middleware + queue workers; use bypass role only for migrations |
| Partitioning grades before data model | Wrong partition key | Design exams first; partition with first create migration |
| Introducing UUID PKs on new tables only | Mixed PK types forever | Decide once at Phase 1 gate |
| Creating ERP schemas wholesale | Over-engineering, empty shells | Phase gates; justify each schema |
| CASCADE on academic FKs | History loss | Keep RESTRICT; never enable cascade on grades/enrollments |
| Disabling RLS for convenience | Tenant leak | Forbidden without human approval |
| Materialized view refresh under load | Locking | Concurrent refresh where supported; queue jobs |
| Intelligence modifying SIS tables | Integrity breach | Keep recommendations human-gated; no auto schema on academic data |
| Large attendance backfill | Downtime | Already partitioned; year partitions via controlled migrations |

---

## Coverage snapshot

```text
Live PostgreSQL 18.2 / sis
├── Implemented SIS foundation ........ ~41 domain OLTP + 3 MVs
├── Intelligence platform ............. 10 tables (not in academic 89)
├── Laravel framework (public) ........ ~10 tables
├── Blueprint not migrated ............ ~40+ tables
└── Master ERP expansion (beyond BP) .. deferred design (Phases 8–16)
```

```text
Estimated gap vs master ERP vision: LARGE
Estimated gap vs current blueprint: ~45–50%
Foundation quality (org→attendance): GOOD (with RLS/CHECK gaps)
```

---

## Evidence artifacts

| Artifact | Location |
|----------|----------|
| Live catalog dump | `storage/app/phase0_discovery.json` (generated 2026-09-10, read-only) |
| Summary extract | `storage/app/phase0_summary.txt` |
| Blueprint SSOT | `.cursor/architecture/database-blueprint.md` |
| Schema helper | `app/Database/SchemaHelper.php` |
| Verifier | `php artisan sis:verify-database` |

---

## Conclusion

The project already has a **solid vocational SIS foundation** on PostgreSQL 18 with correct multi-schema layout, RESTRICT FKs, attendance partitioning, fail-closed RLS on two critical tables, and an isolated intelligence platform. It is **not** yet an Educational ERP database. Phase 0 recommends preserving the existing schema taxonomy, completing blueprint gaps in gated phases, and treating ERP expansions (HR, inventory, medical, full GL, workshops safety model) as explicit follow-on designs—not a single big-bang migration.

**Next document:** `SIS-DATABASE-PHASE-0-GATE.md`  
**STOP:** Human approval required before Phase 1.
