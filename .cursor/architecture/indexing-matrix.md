# Indexing Matrix

> Apply indexes based on real query patterns. Measure with `EXPLAIN ANALYZE` before and after.

## Global Index Rules

1. Every FK column used in JOINs or WHERE clauses gets a B-Tree index.
2. Composite indexes: most selective column first, match query column order.
3. Partial indexes only when filtered subset is significantly smaller (< 30% of table).
4. Covering indexes (`INCLUDE`) only for proven Index Only Scan candidates.
5. BRIN indexes for append-only time-series tables (audit logs).
6. Do not duplicate indexes (e.g., index on `(a)` when `(a, b)` exists and covers `a` queries).

## Per-Table Index Matrix

### `students.students`

| Column(s) | Type | Reason |
|-----------|------|--------|
| student_code | UNIQUE | Direct lookup |
| national_id | UNIQUE (partial) | Identity search |
| status | PARTIAL WHERE status=1 | Active student lists |
| (status) INCLUDE (student_code, full_name) | COVERING | Student list without table access |
| admitted_academic_year_id | B-Tree | Year filter after admission conversion |

### `enrollment.enrollments`

| Column(s) | Type | Reason |
|-----------|------|--------|
| student_id | B-Tree | Enrollment history |
| academic_year_id | B-Tree | Year reports |
| (school_id, academic_year_id) | COMPOSITE | School year reports |
| (student_id, academic_year_id) | COMPOSITE | Student year lookup |
| (student_id, academic_year_id) WHERE status=1 | PARTIAL UNIQUE | One active enrollment |

### `admission.application_periods`

| Column(s) | Type | Reason |
|-----------|------|--------|
| (academic_year_id, school_id) | COMPOSITE | Year/school period lists |
| (school_id, academic_year_id, status) | COMPOSITE | Active-period stage workspace filter |

### `admission.applications`

| Column(s) | Type | Reason |
|-----------|------|--------|
| application_number | UNIQUE | Direct lookup |
| application_period_id | B-Tree | Period membership |
| status | B-Tree | Status filter |
| (application_period_id, status, created_at, id) | COMPOSITE | Stage paginated lists ORDER BY created_at |
| student_id WHERE student_id IS NOT NULL | PARTIAL | Converted link lookup |
| target_school_id | B-Tree | Intended school filter |

### `attendance.daily_section_summary` ⚡ NEW

| Column(s) | Type | Reason |
|-----------|------|--------|
| (section_id, attendance_date) | PRIMARY KEY | One summary per section per day |
| (school_id, attendance_date) | COMPOSITE | School daily dashboard (45K scenario) |
| (academic_year_id, attendance_date) | COMPOSITE | Directorate 20-school report |

### `attendance.sessions`

| Column(s) | Type | Reason |
|-----------|------|--------|
| (section_id, session_date) | COMPOSITE | Section day lookup |
| (academic_year_id, session_date) | COMPOSITE | Year/date reports |
| (school_id) | B-Tree | RLS predicate |
| (school_id, academic_year_id, section_id, subject_id, session_date, period_id) WHERE status=1 NULLS NOT DISTINCT | PARTIAL UNIQUE (R1.8A) | At most one OPEN session per natural key; concurrency authority |

### `attendance.records` ⚡

| Column(s) | Type | Reason |
|-----------|------|--------|
| (student_id, attendance_date) | COMPOSITE | Student attendance history |
| (session_id, student_id) | UNIQUE | Prevent duplicates |
| (academic_year_id, attendance_date) | COMPOSITE | Year/date reports |

### `exams.student_grades` ⚡

| Column(s) | Type | Reason |
|-----------|------|--------|
| (student_id, academic_year_id) | COMPOSITE | Student grade history |
| (subject_id, academic_year_id) | COMPOSITE | Subject results |
| (exam_session_id, student_id) | UNIQUE | One grade per session |

### `audit.audit_logs` ⚡

| Column(s) | Type | Reason |
|-----------|------|--------|
| (entity_type, entity_id) | COMPOSITE | Entity audit trail |
| (user_id, created_at) | COMPOSITE | User activity |
| correlation_id | B-Tree | Request tracing |
| created_at | BRIN | Time-range scans |

### `finance.payments`

| Column(s) | Type | Reason |
|-----------|------|--------|
| student_fee_id | B-Tree | Fee payment lookup |
| idempotency_key | UNIQUE | Prevent duplicate payments |

### `security.user_roles`

| Column(s) | Type | Reason |
|-----------|------|--------|
| user_id | B-Tree | Role lookup on login |
| school_id | B-Tree | RLS policy support |
| (user_id, role_id) | COMPOSITE | Permission checks |

### `timetable.schedules` (re-keyed 2026-10-07)

| Column(s) | Type | Reason |
|-----------|------|--------|
| (section, year, day, period, COALESCE(group,0), COALESCE(week,0)) WHERE active | partial UNIQUE | One lesson per section lane / group / week (groups of one division share a slot) |
| (teacher, year, day, period, COALESCE(week,0)) WHERE active AND lead row | partial UNIQUE | Teacher never double-booked; joined-class rows excluded |
| (co_teacher, year, day, period, COALESCE(week,0)) WHERE active AND lead row | partial UNIQUE | Co-teacher never double-booked |
| (room, year, day, period, COALESCE(week,0)) WHERE active AND room AND lead row | partial UNIQUE | Room never double-booked |

### `timetable` engine tables (2026-10-07)

| Table · column(s) | Type | Reason (query pattern) |
|-------------------|------|------------------------|
| configs · (school, year) | UNIQUE | One settings row read per board load |
| activities · (school, year, status) | B-Tree | Board load: active activities of the school-year |
| activity_sections · section_id / activity_teachers · teacher_id | B-Tree | Activities of a section / teacher (sheets, rule scope) |
| availability · (school, year, status) | B-Tree | Board load; partial UNIQUE per target-slot prevents duplicates |
| constraint_rules · (school, year, status) | B-Tree | Board load of active rules |
| divisions · (school, year), section_id; division_groups · division_id | B-Tree | Groups of the school-year / of a section |
| group_members · enrollment_id; (group, enrollment) WHERE active | B-Tree; partial UNIQUE | Student timetable (groups of an enrollment); no duplicate membership |
| generation_runs · (school, year) WHERE status IN (1,2) | partial UNIQUE | Concurrency guard — one active run per school-year |
| generation_runs · (school, year, created_at DESC) | B-Tree | «عمليات التوليد» list (latest first) |
| versions · (school, year, version_no) / (school, year, status) | UNIQUE / B-Tree | Numbering; version list, current published |
| version_entries · (version, section) / (version, teacher) | B-Tree | Version view, student / effective timetable by section or teacher |

### Timetable workbench (2026-10-08 / 09)

| Table · column(s) | Type | Reason (query pattern) |
|-------------------|------|------------------------|
| timetable.periods · (school, period_number) WHERE status = 1 | partial UNIQUE | Replaces the full UNIQUE: a retired break keeps its row (never deleted) and its number can be reused |
| timetable.test_marks · (school, year, issue_key) WHERE status = 1 | partial UNIQUE | One active mark per issue; marks of a school-year read once per test run |
| organization.rooms · room_type_id | B-Tree | «الغرف الدراسية» filter by type; type-in-use check before retiring a type |
| organization.room_types · (COALESCE(school_id,0), code) | UNIQUE (expression) | Codes unique per school, system types (NULL school) unique among themselves |
| documents.import_batches · (school, created_at DESC) | B-Tree | «عمليات الاستيراد» history (latest first) |
| documents.import_rows · (batch, status) | B-Tree | Preview filter by status, commit of valid rows, error report |

## PostgreSQL Index Examples

```sql
-- Standard B-Tree on FK
CREATE INDEX ix_enrollments_student_id
ON enrollment.enrollments (student_id);

-- Composite for school reports
CREATE INDEX ix_enrollments_school_year
ON enrollment.enrollments (school_id, academic_year_id);

-- Partial index for active students only
CREATE INDEX ix_students_active
ON students.students (school_id, status)
WHERE status = 1;

-- Covering index for list queries
CREATE INDEX ix_students_active_list
ON students.students (status)
INCLUDE (student_code, full_name, national_id)
WHERE status = 1;

-- Partial unique constraint
CREATE UNIQUE INDEX uq_enrollment_active
ON enrollment.enrollments (student_id, academic_year_id)
WHERE status = 1;

-- BRIN for time-series audit
CREATE INDEX ix_audit_logs_created_brin
ON audit.audit_logs USING BRIN (created_at);
```

## When NOT to Index

- Columns with very low selectivity alone (e.g., boolean with 50/50 split)
- Tables with < 1000 rows
- Columns never used in WHERE, JOIN, or ORDER BY
- Duplicate of existing composite index prefix

## Maintenance

```sql
-- After bulk operations
ANALYZE attendance.records;
ANALYZE exams.student_grades;

-- Monitor bloat, reindex when needed (not nightly by default)
REINDEX INDEX CONCURRENTLY ix_attendance_student_date;

-- Autovacuum: tune for high-churn tables
ALTER TABLE attendance.records SET (
  autovacuum_vacuum_scale_factor = 0.05,
  autovacuum_analyze_scale_factor = 0.02
);
```

## Query Optimization Checklist

- [ ] No `SELECT *` in production API
- [ ] No N+1 queries (use eager loading in Laravel)
- [ ] Ran `EXPLAIN ANALYZE` on slow queries
- [ ] Checked for Sequential Scan on large tables
- [ ] Verified Index Only Scan where covering index exists
- [ ] Pagination uses keyset/cursor, not OFFSET on large tables
