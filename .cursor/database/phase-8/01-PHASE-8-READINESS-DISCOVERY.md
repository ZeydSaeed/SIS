# MASTER PHASE 8 — TEACHERS
# READINESS DISCOVERY

---

```text
Date: 2026-09-12
Mode: READ-ONLY
```

## Live

| Surface | Status |
|---------|--------|
| `teachers.teachers` | LIVE (global identity + nullable user_id) |
| `teachers.teacher_schools` | LIVE (school + academic_year) |
| `teachers.teacher_subjects` | LIVE |
| `teachers.teacher_qualifications` | LIVE |
| Attendance / Timetable FK → teachers | LIVE |
| Application Teachers context | **ABSENT** |
| Teachers staff JSON API | **ABSENT** |
| FORCE RLS on teacher_schools / teacher_subjects | **ABSENT** |

## Gaps

```text
1) School-scoped RLS missing on assignment tables
2) No RegisterTeacher / List / Show Application handlers
3) Hard-delete not rejected at DB level
4) Qualifications / subject-assign HTTP deferred past U01
```
