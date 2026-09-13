# Feature: Curriculum

> Phase CUR — subjects, curricula, prerequisites (soft lifecycle). Enrollment prereq enforce CLOSED (U05 history + U06 grade-pass).

## Definition of Done

See [.cursor/architecture/FEATURE-DONE.md](../FEATURE-DONE.md).

## Bounded Context

- **Context:** Curriculum
- **Primary aggregates:** Subject, Curriculum, SubjectPrerequisite
- **Scoped by:** subjects/prerequisites global; curricula school-scoped + academic_year_id

## Planned Use Cases

| Type | Name | Status |
|------|------|--------|
| Command | AddSubjectPrerequisite | ✅ |
| Command | DeactivateSubjectPrerequisite | ✅ |
| Command | CreateSubject | ✅ |
| Command | UpdateSubject | ✅ |
| Command | DeactivateSubject | ✅ |
| Command | ReactivateSubject | ✅ |
| Command | CreateCurriculum | ✅ |
| Command | DeactivateCurriculum | ✅ |
| Command | ReactivateCurriculum | ✅ |
| Command | LinkCurriculumSubject | ✅ |
| Command | DeactivateCurriculumSubject | ✅ |
| Query | ListSubjectPrerequisites | ✅ |
| Query | ListSubjects | ✅ |
| Query | ListCurricula | ✅ |
| Query | ListCurriculumSubjects | ✅ |

## Enrollment prereq (cross-context)

| Type | Name | Status |
|------|------|--------|
| Command | AssignEnrollmentSubject | ✅ U05 + U06 |
| Command | DeactivateEnrollmentSubject | ✅ U05 |
| Query | ListEnrollmentSubjects | ✅ U05 |

## Out of scope

- specialization_id on curricula — HOLD
- Payroll / Ranking-PDF
