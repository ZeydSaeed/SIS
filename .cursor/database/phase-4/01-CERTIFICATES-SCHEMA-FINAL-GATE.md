# PHASE 4.1 — CERTIFICATES SCHEMA FINAL GATE

**Date:** 2026-09-11  
**Unit:** Phase 4.1 Certificates DDL Implementation  
**Mode:** DDL / schema only  
**Predecessors:** 4.0B Design Lock · 4.1A Schema Design Lock · Phase 4 Readiness

```text
PHASE 4.1 CERTIFICATES SCHEMA: PASS
PHASE 4.2 IMPLEMENTATION = NOT AUTHORIZED
```

---

## 1. Summary

Implemented the locked Certificates physical schema under `certificates` with:

- 6 tables
- PK / FK / UNIQUE / CHECK
- school-safe `UNIQUE (id, school_id)` + composite FKs
- Award version pin `(graduation_award_version_id, school_id)` → LIVE graduation index
- RLS ENABLE + FORCE + fail-closed school isolation policies
- reject-delete on all six tables
- immutable template-version content trigger
- **No** Graduation schema mutation
- **No** Application/HTTP/Permission/Job code

---

## 2. Migration Files

| File | Purpose |
|------|---------|
| `database/migrations/2026_09_11_180100_phase41_certificates_templates.php` | templates + template_versions + RLS |
| `database/migrations/2026_09_11_180200_phase41_certificates_core.php` | certificates, issuances, artifacts, jobs + RLS |
| `database/migrations/2026_09_11_180300_phase41_certificates_triggers.php` | reject-delete + template immutability |

**Helper:** `app/Database/CertificatesTenantProtection.php` (mirrors `GraduationTenantProtection`).

---

## 3. Exact Tables / Columns

### `certificates.certificate_templates`

`id`, `school_id`, `certificate_type`, `name`, `status`, `created_at`, `created_by`

### `certificates.certificate_template_versions`

`id`, `school_id`, `template_id`, `version_no`, `locale` (nullable, **no** locale uniqueness), `content_hash`, `template_storage_key`, `effective_from`, `effective_to`, `created_at`, `created_by`

### `certificates.certificates`

`id`, `school_id`, `enrollment_id`, `student_id`, `certificate_type`, `created_at`, `created_by`

### `certificates.certificate_issuances`

`id`, `school_id`, `certificate_id`, `issuance_no`, `graduation_award_version_id`, `graduation_award_id`, `enrollment_id`, `template_version_id`, `lifecycle_status`, `certificate_number`, `verification_code`, `supersedes_issuance_id`, `issued_at`, `issued_by`, `revoked_at`, `revoked_by`, `revoke_reason_ref`, `correlation_id`, `created_at`, `created_by`

### `certificates.certificate_artifacts`

`id`, `school_id`, `issuance_id`, `attempt_no`, `storage_key`, `content_type`, `file_hash`, `byte_size`, `generated_at`, `generator_version`, `is_current`, `created_at`

### `certificates.certificate_generation_jobs`

`id`, `school_id`, `issuance_id`, `job_status`, `attempt_count`, `last_error_ref`, `correlation_id`, `queued_at`, `started_at`, `finished_at`, `created_at`

---

## 4. Constraints / FK Graph

| Constraint | Definition |
|------------|------------|
| Identity UNIQUE | `(school_id, enrollment_id, certificate_type)` |
| Issuance UNIQUE | `(certificate_id, issuance_no)` |
| Number UNIQUE | `(school_id, certificate_number)` |
| Verify UNIQUE | `(verification_code)` **global** |
| Job UNIQUE | `(issuance_id)` |
| Artifact UNIQUE | `(issuance_id, attempt_no)` |
| Current artifact | partial UNIQUE `(issuance_id) WHERE is_current` |
| Template version UNIQUE | `(template_id, version_no)` |
| Award version FK | `(graduation_award_version_id, school_id)` → `graduation.graduation_award_versions(id, school_id)` **RESTRICT** |
| Award FK | `graduation_award_id` → `graduation.graduation_awards(id)` **RESTRICT** |
| Enrollment FKs | `(enrollment_id, school_id)` → enrollments **RESTRICT** |
| Template version FK | `(template_version_id, school_id)` → template_versions **RESTRICT** |
| Supersedes FK | `(supersedes_issuance_id, school_id)` → issuances **RESTRICT** |
| CHECKs | type ≥ 1; lifecycle 1–5; job_status 1–4; template status 1–3; issuance_no/attempt ≥ 1; byte_size ≥ 0 |

**No ON DELETE CASCADE** on official history.

---

## 5. RLS Behavior

For each of the six tables:

```text
ENABLE ROW LEVEL SECURITY
FORCE ROW LEVEL SECURITY
POLICY {table}_school_isolation
  USING / WITH CHECK:
    app.current_school_id present AND school_id matches
```

Graduation RLS unchanged.

---

## 6. Reject-Delete / Immutability

| Object | Behavior |
|--------|----------|
| All six certificate tables | `BEFORE DELETE` → `certificates.reject_hard_delete()` |
| `certificate_template_versions` | `BEFORE UPDATE` blocks changes to template_id, school_id, version_no, content_hash, template_storage_key, locale |

---

## 7. Indexes Created vs Deferred

### Created (integrity / locked)

- All PK / UNIQUE / partial UNIQUE listed above
- `UNIQUE (id, school_id)` on each school-scoped table (composite FK support)

### Deliberately deferred

| Index | Reason |
|-------|--------|
| `(school_id, job_status)` | Evidence-gated; no live worker query yet |
| `(school_id, lifecycle_status)` on issuances | Soft / EXPLAIN later |
| Bare `graduation_award_version_id` non-unique | Soft audit path |

---

## 8. Blueprint Sync

Updated `.cursor/architecture/database-blueprint.md`:

- Certificates section rewritten to 6 LIVE tables
- Stale `graduation_id` sketch marked superseded
- Count: certificates 3 → 6; blueprint total 87 → 90

---

## 9. Tests Executed

```text
php vendor/bin/phpunit -c phpunit.database-pgsql.xml \
  tests/Feature/Database/PostgreSql/Phase41CertificatesSchemaTest.php

result: passed
tests: 10
assertions: 50
```

Coverage:

1. Six tables exist; stale blueprint names absent  
2. RLS ENABLE + FORCE + policies  
3. Reject-delete triggers present  
4. Award version composite FK + core insert path  
5. Certificate number + global verification uniqueness  
6. Artifact current partial unique + job unique  
7. Hard delete rejected  
8. Template version immutability  
9. Cross-school RLS isolation (`sis_rls_tester`)  
10. Graduation not given `graduation_awards_id_school_uidx`

**Support fix:** `PostgreSqlRlsActor` schema grant list now includes `certificates` (required for non-superuser RLS tests).

**Regression:**

```text
Phase3C12GraduationSchemaTest — passed
```

---

## 10. Live PostgreSQL Verification

Executed via `phpunit.database-pgsql.xml` → disposable `sis_test` (`migrate:fresh` through `PostgreSqlIntegrationTestCase`). Catalog assertions in tests confirm tables, RLS, FORCE, policies, triggers, and constraints.

Production `sis` was **not** mutated by this gate’s test run.

---

## 11. Mutation / Scope Audit

```text
PHP APPLICATION (Domain/CQRS/HTTP/Jobs): NONE
MODELS / REPOSITORIES: NONE
PERMISSION.PHP: NONE
GRADUATION DDL: NONE
ENROLLMENT / STUDENT / GRADES DDL: NONE
BACKFILL / DATA MIGRATION: NONE

ALLOWED DELIVERABLES:
  migrations (3)
  CertificatesTenantProtection
  blueprint update
  schema tests
  PostgreSqlRlsActor grant list (+certificates)
  this gate report
```

---

## 12. Deviations from Design Lock

| Item | Status |
|------|--------|
| All six tables | As locked |
| Award composite FK | As locked |
| No Graduation `(award_id, school_id)` unique | As locked |
| Locale uniqueness | None added (as required) |
| Speculative indexes | Not created |
| certificate_type catalog values | Not invented (CHECK ≥ 1 only) |
| Generation job reject-delete | Default reject (as locked) |

**No material deviation.**

---

## 13. Conditions / Follow-ups (non-blocking)

1. Application write-time validation of `graduation_award_id` ↔ award version consistency (4.1A).  
2. Optional future D-SCH-01 Graduation `(id, school_id)` unique — separately authorized.  
3. Operational indexes after worker/EXPLAIN evidence.  
4. Phase 4.2 CQRS / IssueCertificate — **not authorized**.

---

## Final Gate

```text
PHASE 4.1 CERTIFICATES SCHEMA: PASS

PHASE 4.2 IMPLEMENTATION = NOT AUTHORIZED

HTTP: NOT AUTHORIZED
CQRS: NOT AUTHORIZED
JOBS: NOT AUTHORIZED
PERMISSION: NOT AUTHORIZED
GRADUATION MUTATION: NONE

STOP
```
