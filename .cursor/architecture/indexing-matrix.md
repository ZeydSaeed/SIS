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

### `enrollment.enrollments`

| Column(s) | Type | Reason |
|-----------|------|--------|
| student_id | B-Tree | Enrollment history |
| academic_year_id | B-Tree | Year reports |
| (school_id, academic_year_id) | COMPOSITE | School year reports |
| (student_id, academic_year_id) | COMPOSITE | Student year lookup |
| (student_id, academic_year_id) WHERE status=1 | PARTIAL UNIQUE | One active enrollment |

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
