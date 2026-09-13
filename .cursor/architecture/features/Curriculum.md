# Feature: Curriculum

> Phase CUR-U01 — subject prerequisites (soft status). Subjects/curricula HTTP HOLD.

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
| Query | ListSubjectPrerequisites | ✅ |

## Out of scope (U01)

- Subjects / curricula CRUD HTTP
- Enrollment prerequisite enforcement
- Payroll / Ranking-PDF
