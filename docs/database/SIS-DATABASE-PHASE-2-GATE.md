# DATABASE PHASE 2 GATE

```text
DATABASE PHASE 2 GATE
STATUS: PASS WITH CONDITIONS
```

**Date:** 2026-09-10  
**Phase:** 2 — Admission Domain (database)  
**Plan:** [SIS-DATABASE-PHASE-2-PLAN.md](./SIS-DATABASE-PHASE-2-PLAN.md)  
**Authorization:** `APPROVED PHASE 2`  
**Decision lock:** ADR-020 D1–D6

---

## Executive Summary

Phase 2 delivered the blueprint Admission domain: schema `admission`, three tables, CHECK constraints, justified indexes, and fail-closed RLS. No duplicate student identity. Object count remains **87**. Application CQRS conversion handlers and interview/waitlist/history tables were deferred intentionally.

During validation, a mistaken `RefreshDatabase` run against live `sis` damaged the `migrations` catalog and temporarily removed some post-foundation objects. The database was repaired (migration rows restored for existing objects; missing migrations re-applied). Admission objects and fail-closed RLS were re-verified.

---

## Scope

| In scope | Out of scope |
|----------|--------------|
| `admission` schema | Exams/grades |
| 3 blueprint tables | Interviews / waitlist tables / status_history tables |
| SchemaHelper update | Full Admission CQRS/UI feature |
| CHECK + indexes + RLS | ERP domains |
| Domain status enums | Changing blueprint object count |

---

## Implemented Objects

| Object | Notes |
|--------|-------|
| Schema `admission` | Created; added to `SchemaHelper::schemas()` |
| `admission.application_periods` | School + academic year windows |
| `admission.applications` | Applicant lifecycle + nullable `student_id` conversion link |
| `admission.application_documents` | Storage metadata only |
| `ApplicationStatus` / `ApplicationPeriodStatus` | Domain value objects |

---

## Migration List

```text
2026_09_10_131000_phase2_create_admission_schema.php
2026_09_10_131100_phase2_create_admission_tables.php
2026_09_10_131200_phase2_enable_admission_rls.php
```

All **Ran**. No applied historical migrations were rewritten.

---

## Constraints

| Type | Detail |
|------|--------|
| PK | BIGINT IDENTITY on all three tables |
| FK | RESTRICT to years/schools/periods/grade_levels/specializations/students; reviewed_by → users NULL ON DELETE |
| UNIQUE | `application_number` |
| CHECK | period date range; max_applications; period status 0–2; application status 1–9; gender 1–2; document_type > 0 |
| NOT NULL | Per blueprint columns |

---

## Indexes

| Index | Reason |
|-------|--------|
| `(academic_year_id, school_id)` on periods | List by school/year |
| UNIQUE `application_number` | Reference lookup |
| `(application_period_id)`, `(status)` on applications | Pipeline filters |
| Partial `(student_id) WHERE NOT NULL` | Conversion lookup |
| `(application_id)` on documents | Document list |

No partitioning.

---

## RLS

| Table | Policy | Mode |
|-------|--------|------|
| application_periods | `admission_periods_school_isolation` | Fail-closed |
| applications | `admission_applications_school_isolation` | Fail-closed via period |
| application_documents | `admission_documents_school_isolation` | Fail-closed via app→period |

Existing enrollment/attendance policies not weakened (re-confirmed fail-closed after repair).

---

## Relationships

See plan + [03-RELATIONSHIP-MAP.md](./03-RELATIONSHIP-MAP.md). No `admission.students`. Programs mapped to `vocational.specializations`.

---

## Security Tests

| Test | Result |
|------|--------|
| `AdmissionRlsFailClosedTest` (sqlite CI default) | **SKIPPED — ENVIRONMENT PREREQUISITE** (phpunit uses sqlite `:memory:`) |
| Live PG catalog policies (3) | **PASS** (verified on `sis`) |
| Cross-school HTTP feature suite for Admission API | **DEFERRED** (no Admission HTTP module yet) |
| `architecture:validate --fitness` | **PASS** |
| `security:validate` | **PASS** |

---

## Data Integrity Tests

| Check | Result |
|-------|--------|
| `Phase2AdmissionSchemaTest` structural (sqlite) | **PASS** |
| Live PG: 3 tables, 6 CHECKs | **PASS** |
| No `admission.students` / `admission.persons` | **PASS** |
| Pre-constraint data violations | N/A (new empty tables) |

---

## Architecture Tests

| Check | Result |
|-------|--------|
| Domain enums only (no Illuminate in Domain) | **PASS** |
| SchemaHelper reuse (no new helper class) | **PASS** |
| Blueprint still 87 objects | **PASS** (column enrichment only on applications) |

---

## Migration Tests

| Check | Result |
|-------|--------|
| `php artisan migrate` Phase 2 | **PASS** |
| `migrate:status` all Ran after repair | **PASS** |
| `sis:verify-database --no-seed-check` | **PASS** (57 business tables, admission schema) |

---

## Performance Considerations

Low/medium volume expected. No partitions. Indexes limited to blueprint + one partial conversion index.

---

## Risks

| Risk | Status |
|------|--------|
| Accidental RefreshDatabase on live DB | Occurred once; **repaired**; do not repeat |
| Conversion without app handlers | Documented deferral — `student_id` only |
| Specialization school mismatch | App-layer validation later |

---

## Deferred Items

- Interviews table  
- Waitlist table (status=5 used instead)  
- `admission_status_history` table  
- Application → Student → Enrollment Application handlers / Policies / UI  
- Students table RLS (still D6 backlog)  

---

## Known Conditions

1. SQLite PHPUnit cannot assert PG RLS/CHECK catalog — marked SKIPPED, not false PASS.  
2. Live DB required migration-catalog repair after accidental RefreshDatabase against `sis` (**twice** during Phase 2 validation). Objects restored; fail-closed RLS re-applied. New Admission tests refuse RefreshDatabase when database name is `sis`.  
3. Admission HTTP/CQRS feature not in Phase 2.  
4. Blueprint object count unchanged at **87**.

---

## Rollback Strategy

Phase 2 `down()`: drop policies → drop three tables → drop schema `admission`. Does not touch other domains. Prefer forward-fix over rollback in shared environments.

---

## Final Recommendation

Accept Phase 2 with conditions above. Next human-approved phase should **not** start Exams until explicitly authorized. Suggested Phase 3 options: Student RLS expansion, or Assessment/Exams (D5) — human chooses.

```text
HUMAN APPROVAL REQUIRED FOR PHASE 3
```
