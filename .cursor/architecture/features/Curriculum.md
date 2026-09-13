# Feature: Curriculum

> Phase CUR — subject catalog + prerequisites (soft status). Curricula HTTP HOLD.

## Definition of Done

See [.cursor/architecture/FEATURE-DONE.md](../FEATURE-DONE.md).

## Bounded Context

- **Context:** Curriculum
- **Primary aggregate:** SubjectPrerequisite (edge)
- **Scoped by:** global subject catalog; HTTP requires school context for AuthZ

## Planned Use Cases

| Type | Name | Status |
|------|------|--------|
| Command | AddSubjectPrerequisite | ✅ |
| Command | DeactivateSubjectPrerequisite | ✅ |
| Command | CreateSubject | ✅ |
| Command | DeactivateSubject | ✅ |
| Query | ListSubjectPrerequisites | ✅ |
| Query | ListSubjects | ✅ |

## Out of scope

- Subjects PATCH / reactivate — HOLD (U03+)
- Curricula HTTP — HOLD
- Enrollment prerequisite enforcement — HOLD
- Payroll / Ranking-PDF
