# Feature: Curriculum

> Phase CUR — subjects, curricula, prerequisites. Enrollment prereq CLOSED (U05/U06). Curriculum specialization + name PATCH CLOSED (U07–U09).

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
| Command | CreateCurriculum | ✅ (+ optional specialization_id U07) |
| Command | UpdateCurriculum | ✅ U08/U09 (name + specialization) |
| Command | DeactivateCurriculum | ✅ |
| Command | ReactivateCurriculum | ✅ |
| Command | LinkCurriculumSubject | ✅ |
| Command | DeactivateCurriculumSubject | ✅ |
| Query | ListSubjectPrerequisites | ✅ |
| Query | ListSubjects | ✅ (+ includeInactive for web) |
| Query | ListCurricula | ✅ (+ includeInactive for web) |
| Query | ListCurriculumSubjects | ✅ (+ includeInactive for web) |

## Web (Inertia)

| Surface | Status |
|---------|--------|
| `GET /curriculum` page | ✅ list + server filters + pagination |
| `GET /curriculum/curricula/{id}` detail tabs | ✅ plan / subjects / prerequisites / students |
| Create / Edit / Deactivate / Reactivate curriculum | ✅ web routes → Application handlers |
| Auto-link specialization subjects on create | ✅ CreateCurriculumHandler + specialization_subjects |
| Create / Edit / Deactivate / Reactivate subject | ✅ |
| Link / Deactivate / Reactivate curriculum subjects | ✅ |
| Prerequisites web store/deactivate/reactivate | ✅ |
| Enrollment subject assign from curriculum detail | ✅ AssignEnrollmentSubjectHandler |
| Branch → department SSOT | ✅ `AdmissionCatalogReference` |
| Subject catalog SSOT by branch/dept | ✅ `CurriculumSubjectCatalogReference` + seeder |
| Demo curricula seed (spec × grade) | ✅ `CurriculumPlansSeeder` |
| Window registry curriculum.list / show | ✅ |

## Enrollment prereq (cross-context)

| Type | Name | Status |
|------|------|--------|
| Command | AssignEnrollmentSubject | ✅ U05 + U06 |
| Command | DeactivateEnrollmentSubject | ✅ U05 |
| Query | ListEnrollmentSubjects | ✅ U05 |

## Out of scope

- PATCH grade_level / academic_year after create — HOLD
- Payroll / Ranking-PDF
