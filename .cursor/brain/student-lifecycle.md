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

A student may have only one active enrollment per academic year:

```
UNIQUE (student_id, academic_year_id) WHERE status = 'ACTIVE'
```

(PostgreSQL partial unique index)

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
