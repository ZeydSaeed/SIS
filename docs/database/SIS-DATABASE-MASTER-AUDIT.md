# SIS Database Master Audit

**Phase:** 0 — Discovery (READ-ONLY) — **REFRESH**  
**Date:** 2026-09-12  
**Prior audit:** 2026-09-10 (superseded for live counts; historical narrative retained where still true)  
**Database:** `sis` @ PostgreSQL 18.2  
**Status:** COMPLETE — no schema changes performed in this refresh  
**Mode:** READ-ONLY discovery only

**Sources:**
- Live PostgreSQL catalog (2026-09-12 inventory)
- Laravel migrations (`database/migrations` — 47 applied)
- `.cursor/architecture/database-blueprint.md` (**87** blueprint objects SSOT)
- `.cursor/architecture/SIS-CONSTITUTION.md` / ADR-020
- Existing `docs/database/*` Phase 1–3C / Phase 4.1 gates
- Master Phase 7 governance (`.cursor/database/phase-7*`)
- Master ERP prompt domains (gap analysis only — not greenfield restart)

```text
This refresh does NOT authorize implementation.
This refresh does NOT reopen completed Phase 0–3C / 4.1 / Phase 7.2 unit closures.
This refresh does NOT create tables, migrations, or RLS changes.
```

---

## A. Current State (live 2026-09-12)

### Connection

| Item | Value |
|------|-------|
| Engine | PostgreSQL **18.2** (x86_64-windows, MSVC) |
| Database name | `sis` |
| Connected user | `postgres` (from `.env`) |
| Laravel | **13.30.1** / PHP **8.4.16** |
| Driver | `pgsql` |
| Host / port | `127.0.0.1:5432` |
| Migrations applied | **47** |
| Password in prompt | Matches local convention (`root`) — **do not commit secrets** |

### Extensions

| Extension | Purpose |
|-----------|---------|
| `plpgsql` | Default PL |
| `pg_stat_statements` | Query telemetry / intelligence |

Not installed (evaluated, not required by this refresh): `pgcrypto`, `citext`, `uuid-ossp`, `pg_trgm`.

### Schemas (26)

```text
academic, admission, attendance, audit, certificates, communication,
curriculum, documents, enrollment, exams, finance, graduation, guardians,
intelligence, organization, promotion, public, reports, results, security,
students, teachers, timetable, transfers, vocational, workflow
```

### Tables by schema (ordinary `pg_tables`)

| Schema | Count | Notes |
|--------|------:|-------|
| academic | 5 | years, terms, grade_levels, holidays, system_settings |
| admission | 3 | periods, applications, documents — FORCE RLS |
| attendance | 4 | sessions, records, records_default, daily_section_summary |
| audit | 2 | outbox_messages, idempotency_keys |
| certificates | 6 | Phase 4.1 LIVE — FORCE RLS |
| curriculum | 3 | subjects, curricula, curriculum_subjects (**prerequisites missing**) |
| enrollment | 4 | classes, sections, enrollments, enrollment_subjects |
| exams | 5 | exam_types, exams, exam_sessions, exam_enrollments, student_grades |
| graduation | 14 | Phase 3C.12 LIVE — FORCE RLS |
| guardians | 3 | |
| intelligence | 10 | isolated from SIS integrity writers |
| organization | 6 | ministries → rooms |
| public | 11 | Laravel auth/cache/queue |
| security | 6 | roles, permissions, scopes, audit |
| students | 3 | students, contacts, addresses (**student_documents missing**) |
| teachers | 4 | not full HR |
| timetable | 1 | **periods only** — schedules missing |
| vocational | 3 | |
| results / promotion / transfers / documents / finance / communication / workflow | **0** | schemas reserved empty |
| **TOTAL** | **93** | includes framework + intelligence + partition child |

### Partitions

| Parent | Child | Notes |
|--------|-------|-------|
| `attendance.records` | `records_default` | LIST/`academic_year_id` strategy; default child present |
| `exams.student_grades` | (partitioned `relkind=p`) | FORCE RLS on parent; Phase 3B |

### RLS

| Metric | Value |
|--------|------:|
| Tables with `relrowsecurity` (ordinary) | **29** listed |
| Notable FORCE RLS | admission.*, exams.exams/sessions/enrollments, certificates.*, graduation.*, attendance.sessions / daily_section_summary |
| Partitioned grades | `exams.student_grades` FORCE RLS (`relkind=p` — not in ordinary RLS list) |
| `enrollment.enrollments` | RLS **ON**, FORCE **OFF** |

### Constraints / keys (catalog counts)

| Kind | Count |
|------|------:|
| Foreign keys | **167** |
| CHECK constraints | **644** |
| Materialized views | **3** (`reports.mv_*`) |

### Materialized views

```text
reports.mv_daily_attendance
reports.mv_directorate_school_comparison
reports.mv_school_student_statistics
```

---

## B. Existing SIS Coverage

| Domain | Live status | Evidence |
|--------|-------------|----------|
| Organization / multi-school | **LIVE** | organization.* |
| Academic calendar | **LIVE** | academic.* |
| Vocational structure | **LIVE** | vocational.* |
| Students / guardians | **LIVE** (partial enrichment) | students.*, guardians.* |
| Admission | **LIVE** | admission.* + FORCE RLS |
| Enrollment | **LIVE** | enrollment.* |
| Teachers | **LIVE** (not HR) | teachers.* |
| Curriculum | **LIVE** (no prerequisites table) | curriculum.* |
| Timetable | **PARTIAL** | periods only |
| Attendance | **LIVE** + partitioned | attendance.* |
| Exams / sessions / enrollments | **LIVE** + app CQRS (Phase 7.1–7.2) | exams.* |
| Student grades | **LIVE** partitioned + writers | exams.student_grades |
| Results / GPA / transcripts tables | **EMPTY schema** | results has 0 tables (app/docs Phase 3C exist; physical tables gap) |
| Graduation | **LIVE** | graduation.* (14) |
| Certificates | **LIVE** | certificates.* (6) |
| Security / RBAC | **LIVE** | security.* + public.users |
| Audit outbox / idempotency | **LIVE** | audit.* |
| Intelligence / self-healing | **LIVE** (recommend / Tier-1) | intelligence.* |
| Finance / HR / inventory / medical / transport / library | **NOT LIVE** | reserved or not created |

### Master Phase 7 application coverage (governance)

| Subphase | Status |
|----------|--------|
| Phase 7.1 Exam Administration | **CLOSED** |
| Phase 7.2 Session/Enrollment lifecycle + Batch 6 (U01–U16) | **CLOSED / ACCEPTED WITH CONDITIONS** |
| Phase 7.3 Grade hardening | **CLOSED / ACCEPTED WITH CONDITIONS** |
| Phase 7.4–7.6 | **CONDITIONAL / DEFERRED** |
| Phase 7.7 Final DB gate | **CLOSED / ACCEPTED WITH CONDITIONS** |
| Master Phase 7 Final Closure | **CLOSED / ACCEPTED WITH CONDITIONS** (`phase-7/10`) |

---

## C. Missing Domains (vs master ERP prompt)

| Prompt domain | Gap classification | Priority vs current program |
|---------------|--------------------|-----------------------------|
| Full person SSOT (`persons`) | Optional redesign | LOW now — avoid identity rewrite |
| Student documents / status history / notes | Blueprint / enrichment gap | MEDIUM |
| Curriculum prerequisites | Blueprint gap | MEDIUM |
| Timetable schedules / block scheduling / section_batches | Major gap | HIGH after Phase 7 close |
| Workshop safety capacity / equipment | Vocational ops gap | HIGH after scheduling |
| Results / ranking / transcript **tables** | Schema empty despite Phase 3C docs | **HIGH** (align with Phase 7.4/7.5 gates) |
| Promotion / transfers tables | Empty schemas | MEDIUM |
| Documents / finance / communication / workflow tables | Empty schemas | MEDIUM–HIGH later |
| HR / payroll | Not created | Later ERP |
| Behavior / activities / assemblies / duties / volunteering | Not created | Later ops |
| Medical / SEN / IEP | Not created | Later + HIGHLY_RESTRICTED |
| Internship / alumni / library / transport | Not created | Later |
| Inventory / facilities / full GL | Not created | Later ERP |
| `audit.audit_logs` domain table | Missing (outbox exists) | MEDIUM |

---

## D. Duplicates

| Risk | Finding |
|------|---------|
| Parallel “Phase 0–21 mega plan” vs live program | **Conflict of numbering** — must not restart greenfield |
| `docs/database` catalogs vs blueprint | Catalog sections still list some tables as “reserved” that are now LIVE — this refresh corrects that |
| Teachers vs full employees | Teachers are **not** HR employees — intentional; do not duplicate into `hr` without ADR |
| Grades vs results | `student_grades` = ledger; `results.*` = aggregation — keep separated (P7 design) |
| Blueprint count 87 vs live 93 | Intelligence + framework + partition children inflate live count; blueprint remains academic SSOT |

---

## E. Conflicts

| Conflict | Severity | Resolution posture |
|----------|----------|--------------------|
| Mega-prompt Phase 0–21 vs Master Phase 7 roadmap | HIGH if ignored | **Follow Master Phase 7 / ADR-020 / blueprint** — treat mega-prompt as gap backlog |
| Prompt UUID PK preference vs D1 BIGINT IDENTITY | HIGH if changed | **KEEP BIGINT IDENTITY** (ADR-020) |
| Prompt wants many new schemas immediately | HIGH over-engineering | Create schemas only with justified gates |
| `results` empty while Phase 3C architecture docs exist | MEDIUM | Implementation/physicalization gated — do not invent tables without AuthZ |
| HTTP writers for Phase 7.2 | By design NOT AUTHORIZED | Preserve |
| `exam.session.cancel` | FORBIDDEN | Preserve |
| enrollment RLS FORCE=OFF vs exams FORCE=ON | MEDIUM consistency | Future hardening — not Phase 0 work |

---

## F. Migration Risks

| Risk | Notes |
|------|-------|
| Rewriting old migrations | **FORBIDDEN** — 47 applied; additive only |
| Greenfield rebuild of `sis` | **Data loss / downtime CRITICAL** — prohibited |
| Disabling RLS / dropping FKs | Destructive — human approval required |
| Filling `results` without Phase 7.4/7.5 AuthZ | Architecture conflict with Master Lock conditionals |
| Jumping to HR/Finance without Phase 8 AuthZ | Scope explosion / integrity risk |
| Attendance year partitions beyond default | Capacity risk if not automated before scale |
| Inventing `exam.session.cancel` / DEFAULT grades partition | Forbidden forever |

---

## G. What NOT to do next

```text
DO NOT restart Phase 0–21 as if the database does not exist
DO NOT create all ERP schemas in one wave
DO NOT change PK strategy to UUID without ADR + human approval
DO NOT invent exam.session.cancel
DO NOT create DEFAULT student_grades partition
DO NOT open Phase 8 without explicit Phase 8 start AuthZ
DO NOT physicalize results.* without Phase 7.4/7.5 AuthZ
DO NOT DROP / TRUNCATE / DISABLE RLS
```

---

## H. Recommended next program step (see Phase 0 Gate)

```text
Master Phase 7: CLOSED / ACCEPTED WITH CONDITIONS

Current deserved choice (human AuthZ required):
  Phase 7.4 Design Ballot  OR  Timetable/vocational  OR  Phase 8 start
  — NOT mega-prompt Phase 1 Core/Identity rebuild
  — NOT silent HR/Finance/Inventory wave
```

Detail: [SIS-DATABASE-PHASE-0-GATE.md](./SIS-DATABASE-PHASE-0-GATE.md)
