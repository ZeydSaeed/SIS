# Curriculum Create — Final Closure Gate

**Date:** 2026-09-29  
**Feature:** Curriculum create (web + Application)  
**Status:** **PASS**

## Scope closed

1. Create curriculum (school + academic year + grade + optional specialization)
2. Auto-link active `vocational.specialization_subjects` into `curriculum.curriculum_subjects` on create
3. Web Inertia page create / edit / deactivate / reactivate
4. SSOT branch→department + subject catalog seeders
5. Demo plans seeder (`CurriculumPlansSeeder`)

## Evidence

| Check | Result |
|-------|--------|
| `CreateCurriculumHandler` links catalog subjects in same UoW | PASS |
| `SpecializationCatalogPort::listActiveSubjectTemplates` | PASS |
| Web `POST /curriculum/curricula` | PASS (`PhaseUiCurCurriculumPagePostgreSqlTest` 3/3) |
| API create with specialization auto-links | PASS (`PhaseCurCreateCurriculumLinksCatalogHttpApiPostgreSqlTest` 1/1) |
| Guest redirected from `/curriculum` | PASS |
| Manager Inertia page props | PASS |
| Demo seed curricula + links | PASS (96 curricula / 528 links on demo school) |
| `architecture:feature-check Curriculum` | PASS |

## Final Gate Status

**PASS**
