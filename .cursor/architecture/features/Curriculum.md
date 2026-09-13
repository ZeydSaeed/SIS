# Feature: Curriculum

> Phase CUR — subjects, curricula, prerequisites (soft status). Enrollment prereq enforce HOLD.

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
| Command | DeactivateSubject | ✅ |
| Command | CreateCurriculum | ✅ |
| Command | DeactivateCurriculum | ✅ |
| Command | LinkCurriculumSubject | ✅ |
| Command | DeactivateCurriculumSubject | ✅ |
| Query | ListSubjectPrerequisites | ✅ |
| Query | ListSubjects | ✅ |
| Query | ListCurricula | ✅ |
| Query | ListCurriculumSubjects | ✅ |

## Out of scope

- Subjects PATCH / reactivate — HOLD
- specialization_id on curricula — HOLD
- Enrollment prerequisite enforcement — HOLD
- Payroll / Ranking-PDF
