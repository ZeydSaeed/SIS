# RLS Policies — 20 School Multi-Tenant Isolation

> **Priority:** P0 for 45K scenario — enable before student data exists  
> **Reference:** `security-audit-resilience.md`

## Principle

```
Application Authorization (primary)
        +
PostgreSQL RLS (defense-in-depth)
```

Set session variable on each request after auth:

```php
// Middleware: SetSchoolContext
DB::statement("SELECT set_config('app.current_school_id', ?, false)", [$user->schoolId]);
DB::statement("SELECT set_config('app.current_directorate_id', ?, false)", [$user->directorateId]);
DB::statement("SELECT set_config('app.current_role', ?, false)", [$user->roleCode]);
```

## Tables Requiring RLS

| Table | Scope Column | Roles Affected |
|-------|-------------|----------------|
| enrollment.enrollments | school_id | Principal, Teacher, Registrar |
| attendance.records | school_id | Teacher, Principal |
| attendance.daily_section_summary | school_id | Principal, Directorate |
| exams.student_grades | via enrollment | Teacher, Principal |
| finance.student_fees | via enrollment | Accountant, Principal |
| students.students | via enrollment join | All school-scoped |

## Policy Templates

### School Staff — Own School Only

```sql
ALTER TABLE enrollment.enrollments ENABLE ROW LEVEL SECURITY;

CREATE POLICY school_isolation ON enrollment.enrollments
    FOR ALL
    USING (
        current_setting('app.current_role', true) IN ('ministry', 'directorate')
        OR school_id = nullif(current_setting('app.current_school_id', true), '')::BIGINT
    );
```

### Directorate — All Schools in Province

```sql
CREATE POLICY directorate_access ON enrollment.enrollments
    FOR SELECT
    USING (
        current_setting('app.current_role', true) = 'directorate'
        AND school_id IN (
            SELECT id FROM organization.schools
            WHERE directorate_id = nullif(current_setting('app.current_directorate_id', true), '')::BIGINT
        )
    );
```

### Ministry — Full Access

```sql
CREATE POLICY ministry_access ON enrollment.enrollments
    FOR ALL
    USING (current_setting('app.current_role', true) = 'ministry');
```

### Teacher — Assigned Sections Only

```sql
CREATE POLICY teacher_section ON attendance.records
    FOR ALL
    USING (
        current_setting('app.current_role', true) = 'teacher'
        AND session_id IN (
            SELECT s.id FROM attendance.sessions s
            JOIN enrollment.sections sec ON sec.id = s.section_id
            WHERE sec.homeroom_teacher_id = nullif(current_setting('app.current_user_id', true), '')::BIGINT
               OR s.teacher_id = nullif(current_setting('app.current_user_id', true), '')::BIGINT
        )
    );
```

## Index Requirements for RLS

RLS policies filter on these columns — **must have indexes**:

```sql
CREATE INDEX ix_enrollments_school_id ON enrollment.enrollments (school_id);
CREATE INDEX ix_attendance_records_school_date ON attendance.records (school_id, attendance_date);
CREATE INDEX ix_schools_directorate ON organization.schools (directorate_id);
```

## Bypass for Migrations & Seeds

```sql
-- Run as superuser or table owner during migrations
ALTER TABLE enrollment.enrollments DISABLE ROW LEVEL SECURITY;
-- ... migration ...
ALTER TABLE enrollment.enrollments ENABLE ROW LEVEL SECURITY;
```

Laravel migrations: use `DB::statement()` as postgres superuser.

## Testing RLS

```sql
-- Simulate school 5 admin
SET app.current_role = 'principal';
SET app.current_school_id = '5';
SELECT COUNT(*) FROM enrollment.enrollments;  -- must only show school 5

-- Simulate school 3 admin — must NOT see school 5 data
SET app.current_school_id = '3';
SELECT COUNT(*) FROM enrollment.enrollments WHERE school_id = 5;  -- must return 0
```

## Phase A Checklist

- [ ] Policies defined for enrollment.enrollments
- [ ] Policies defined for attendance.records (Phase C)
- [ ] Middleware sets session variables on every request
- [ ] Indexes on school_id, directorate_id exist
- [ ] Penetration test: cross-school access blocked

## مراجع

- Phase A: `phases/PHASE-A-FOUNDATION.md`
- Security: `security-audit-resilience.md`
- Blueprint: school_id denormalized on attendance.records for RLS performance
