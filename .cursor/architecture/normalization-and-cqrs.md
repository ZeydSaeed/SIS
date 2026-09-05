# Normalization (1NF → 4NF) & CQRS

> **OLTP default:** 3NF in PostgreSQL  
> **Reporting:** Controlled denormalization + Materialized Views  
> **Application:** CQRS-lite separation of reads and writes

---

## 1NF — First Normal Form

**Rule:** Atomic values, no repeating groups, unique row identity.

### ❌ Violates 1NF

```
students
---------
id | name | subject1 | subject2 | mark1 | mark2
```

### ✅ 1NF Compliant

```
students (id, name)
enrollments (student_id, year, section_id)
student_grades (enrollment_id, subject_id, grade)
```

**SIS rule:** One fact per column. No `phone1, phone2` — use `student_contacts` table.

---

## 2NF — Second Normal Form

**Rule:** 1NF + every non-key attribute depends on **full** primary key (no partial dependency).

### ❌ Violates 2NF (composite key)

```
enrollment_subjects (enrollment_id, subject_id, student_name, subject_name)
-- student_name depends only on enrollment_id
-- subject_name depends only on subject_id
```

### ✅ 2NF Compliant

```
enrollments → students (name)
enrollment_subjects → subjects (name)
```

**SIS rule:** Junction tables contain only keys + relationship-specific attributes (e.g., `is_elective`).

---

## 3NF — Third Normal Form

**Rule:** 2NF + no transitive dependency (non-key → non-key).

### ❌ Violates 3NF

```
enrollments (id, student_id, school_id, school_name, directorate_name)
-- school_name depends on school_id, not enrollment id
```

### ✅ 3NF Compliant

```
enrollments (id, student_id, school_id)
schools (id, name, directorate_id)
directorates (id, name)
```

**SIS rule:** Store IDs, join for names in queries. Denormalize `school_id` on `attendance.records` **only** for RLS performance (documented exception).

---

## 4NF — Fourth Normal Form

**Rule:** 3NF + no multi-valued dependencies (MVD).

### ❌ Violates 4NF

```
teachers (id, name, subject_a, subject_b, subject_c)
-- Multiple independent multi-valued facts in one row
```

### ✅ 4NF Compliant

```
teachers (id, name)
teacher_subjects (teacher_id, subject_id, academic_year_id, school_id)
```

**SIS rule:** Separate tables for independent many-to-many relationships (student↔guardian, teacher↔subject, enrollment↔subject).

---

## BCNF / 4NF+ — When We Stop

For SIS OLTP, **3NF + selective 4NF** is the target. We do not pursue 5NF/6NF — unnecessary complexity.

| Form | SIS Application |
|------|-----------------|
| 1NF | All tables |
| 2NF | All junction/transactional tables |
| 3NF | Default for all OLTP schemas |
| 4NF | teacher_subjects, student_guardians, curriculum_subjects |
| Denormalized | daily_section_summary, materialized views, cache only |

---

## Controlled Denormalization (Allowed)

| Table | Denormalized Column | Why |
|-------|---------------------|-----|
| attendance.records | school_id | RLS + school reports without join |
| attendance.daily_section_summary | all aggregates | Pre-computed read model |
| reports.mv_* | all columns | Analytics read model |

Every denormalization must be documented in blueprint + ADR if non-obvious.

---

## CQRS — Command Query Responsibility Segregation

### Full CQRS (NOT recommended now)

```
Separate write DB + read DB + event bus
```

**Overkill for 45K students.** No Kafka, no separate write/read databases at this stage.

### CQRS-Lite (Recommended)

```
Commands (writes)                    Queries (reads)
─────────────────                    ───────────────
EnrollStudentAction                  SchoolDashboardQuery
RecordAttendanceBatch                StudentHistoryQuery
EnterGradesBatch                     DirectorateReportQuery
TransferStudent                      SearchStudentsQuery
         │                                    │
         ▼                                    ▼
   PostgreSQL Primary              Primary or Read Replica
   (transactional)                 (MV, summary tables, cache)
```

### Command Rules

- Mutate state through Actions/Services
- Use DB transactions
- Emit events for side effects (cache, summary, audit)
- Return minimal data (id, status)

### Query Rules

- No mutations
- Read from: summary tables → MV → replica → primary (last resort)
- Optimized for display — DTOs not full models
- Cache reference data per cache-invalidation.md

---

## Reporting Tier (CQRS Read Side)

```
Real-time operational     → Normalized OLTP tables
Frequently viewed         → daily_section_summary
Dashboard / school        → Materialized Views (5–8 min stale)
Heavy analytics           → Read Replica + MV
Historical archive        → Detached partitions / archive DB
```

---

## Example: Attendance CQRS Flow

### Command Side

```
RecordSectionAttendanceCommand
    → AttendanceBatchService
    → INSERT attendance.records (batch)
    → UPSERT daily_section_summary
    → Dispatch AttendanceRecorded event
    → Invalidate dashboard cache
```

### Query Side

```
SchoolDashboardQuery
    → READ daily_section_summary (900 rows)
    → OR mv_daily_attendance
    → Never: COUNT(*) on 45M attendance.records
```

---

## Consistency Model

| Operation | Model |
|-----------|-------|
| Record attendance | **Strong** (transaction) |
| daily_summary update | **Eventual** (< 5 seconds) |
| Materialized view | **Eventual** (5m – 24h) |
| Redis dashboard | **Eventual** (TTL 5m) |

UI shows `last_updated_at` on all dashboards.

---

## Related

- [01-principles-and-layers.md](./01-principles-and-layers.md)
- [laravel-architecture.md](./laravel-architecture.md)
- [batch-write-patterns.md](./batch-write-patterns.md)
- [adr/ADR-008-cqrs-lite.md](./adr/ADR-008-cqrs-lite.md)
