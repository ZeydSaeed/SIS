# Logical Data Architecture

> **Separation:** Logical model (stable, technology-agnostic) vs Physical implementation (PostgreSQL-specific, replaceable)

---

## Logical Model — Core Entities

```text
Organization
  Ministry → Directorate → School → Branch → Department → Room

Academic
  AcademicYear → Term → GradeLevel

People
  Student, Guardian, Teacher, User (system account)

Vocational
  Specialization → Track

Enrollment
  Class → Section → Enrollment → EnrollmentSubject

Curriculum
  Subject → Curriculum → CurriculumSubject

Operations
  Timetable → AttendanceSession → AttendanceRecord
  Exam → ExamSession → StudentGrade

Lifecycle
  Admission → Promotion → Transfer → Graduation → Certificate

Supporting
  Document, Payment, Notification, AuditLog, ApprovalWorkflow
```

These entities and relationships **survive** if PostgreSQL is replaced.

---

## Logical Relationships (Cardinality)

| From | To | Cardinality | Business Rule |
|------|-----|-------------|---------------|
| School | Section | 1:N | 45 sections/school at baseline |
| Student | Enrollment | 1:N | One per academic year |
| Enrollment | EnrollmentSubject | 1:N | ~15 subjects |
| Student | Guardian | N:M | Via student_guardians |
| Section | AttendanceSession | 1:N | Daily sessions |
| ExamSession | StudentGrade | 1:N | One grade per student |

Full diagram: [erd-overview.md](./erd-overview.md)

---

## Physical Implementation — PostgreSQL (Current)

| Logical Concept | Physical Implementation |
|-----------------|------------------------|
| Entity ID | BIGINT GENERATED ALWAYS AS IDENTITY |
| External ID | UUID public_id |
| Status enums | SMALLINT + app enum |
| History | effective_from / effective_to (not soft delete) |
| High-volume facts | LIST partition by academic_year_id |
| Time-series audit | RANGE partition by created_at |
| Multi-school isolation | RLS on school_id |
| Dashboard reads | daily_section_summary + Materialized Views |
| File storage | Metadata in DB, blobs in object storage |
| Cache | Redis — not source of truth |

---

## What Changes If DBMS Changes

| Layer | Action |
|-------|--------|
| Logical model | **Keep** — blueprint, dictionary, ERD |
| Migrations | Rewrite for new DBMS |
| Partition syntax | Adapt (PG LIST → equivalent) |
| RLS | Adapt (PG RLS → app-level or DB equivalent) |
| MV | Adapt syntax |
| Indexes | Re-evaluate per INDEX-GOVERNANCE |
| ADRs | New ADR documenting migration |

**Logical blueprint remains authoritative** during any platform change.

---

## CQRS Logical Separation

| Side | Logical Responsibility | Current Physical |
|------|------------------------|------------------|
| Commands | Mutate enrollment, attendance, grades | Services → PostgreSQL Primary |
| Queries | Dashboards, search, reports | Summary tables, MV, Replica, Redis |

See [normalization-and-cqrs.md](./normalization-and-cqrs.md)

---

## Related

- [database-blueprint.md](./database-blueprint.md) — physical schema detail
- [database-dictionary.md](./database-dictionary.md)
- [adr/ADR-001-postgresql.md](./adr/ADR-001-postgresql.md)
