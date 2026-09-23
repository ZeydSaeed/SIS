# Student Lifecycle

## Entity Flow

```
Student
   ↓
Enrollment (per Academic Year)
   ↓
Section → Class → Specialization → Curriculum
   ↓
Subject
   ├── Teacher Assignment
   ├── Timetable
   ├── Attendance
   └── Exam
          ↓
        Grade / Result
          ↓
   Promotion / Transfer / Graduation
          ↓
      Certificate
```

## Temporal Layer

Every academic operation is anchored to an Academic Year:

```
Academic Year
      │
      ├── School
      ├── Grade Level
      ├── Branch
      ├── Specialization
      ├── Curriculum
      └── Enrollment
              │
              ├── Attendance
              ├── Exams
              ├── Grades
              ├── Promotion
              ├── Transfer
              └── Graduation
```

## Historical Data — Do NOT Overwrite

When a student moves schools across years:

```
2024 → School A
2025 → School B
2026 → School C
```

Do **not** update `student.school_id` and lose history.

Instead, use `enrollment_history`:

```
student
    └── enrollment (per academic_year)
          ├── 2024 → School A, Section 3
          ├── 2025 → School B, Section 1
          └── 2026 → School C, Section 2
```

## Status Transitions

Use explicit status fields — not blanket soft-delete:

```
status          : ACTIVE | INACTIVE | GRADUATED | TRANSFERRED | WITHDRAWN
effective_from  : date enrollment became active
effective_to    : date enrollment ended (nullable)
archived_at     : timestamp when record was archived (nullable)
```

Official academic records are **never hard-deleted**.

## Enrollment Uniqueness

A student may have only one **active** enrollment per academic year:

```
UNIQUE (student_id, academic_year_id) WHERE status = 1 AND effective_to IS NULL
```

(PostgreSQL partial unique index)

Mid-year placement changes (class/section/branch/department) **do not overwrite** the active row. The prior row is closed as `status = SUPERSEDED (5)` with `effective_to`, and a **new** active enrollment row is inserted for the same year.

```
student
    └── academic year 2025-2026
          ├── enrollment A — صف 1 / شعبة أ — superseded (effective_to set)
          └── enrollment B — صف 2 / شعبة ب — active (effective_to NULL)
```

## Key Lifecycle Events

| Event | Tables Involved | Async? |
|-------|----------------|--------|
| Admission | `admission.applications`, `students.students` | No |
| Enrollment | `enrollment.enrollments` | No |
| Daily Attendance | `attendance.records` | No |
| Grade Entry | `exams.student_grades` | No |
| Bulk Certificate | `certificates.*` | **Yes — Queue** |
| Mass Notification | `communication.messages` | **Yes — Queue** |
| Bulk Import | `students.*`, `enrollment.*` | **Yes — Queue** |
| Report Generation | `reports.*` | **Yes — Queue** |
| Graduation | `graduation.records` | No |
| Transfer | `transfers.records`, new enrollment | No |
