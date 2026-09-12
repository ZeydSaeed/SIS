# 01 — Schema Catalog

**Status:** Phase 1 (ADR-020 locked)  
**Refresh:** 2026-09-12 live catalog  
**Rule:** Create a schema only with documented responsibility. Empty reserved schemas already created for blueprint modules may remain until tables land.  
**D2:** Do not rename live schemas for cosmetic alignment with external naming lists.

---

## A. Live schemas (implemented or reserved)

| Schema | Responsibility | Tables now? | Notes |
|--------|----------------|-------------|-------|
| `public` | Laravel auth, cache, queues, migrations | Yes (11) | Framework; not SIS domain |
| `organization` | Ministry → school → branch → department → room | Yes (6) | `branches` = campuses (D2) |
| `academic` | Years, terms, grade levels, holidays, settings | Yes (5) | Cross-cutting academic calendar |
| `vocational` | Specializations, tracks, subject links | Yes (3) | Vocational structure |
| `students` | Student master + contacts/addresses | Yes (3) | Missing documents table |
| `guardians` | Guardians and student links | Yes (3) | |
| `enrollment` | Classes, sections, enrollments, subject links | Yes (4) | RLS ON; FORCE OFF |
| `teachers` | Teacher profiles and assignments | Yes (4) | Not full HR |
| `curriculum` | Subjects, curricula, links | Yes (3) | Missing prerequisites |
| `timetable` | Periods and (future) schedules | Partial (1) | Only periods |
| `attendance` | Sessions, records, daily summary | Yes (4) | Partitioned records |
| `admission` | Application periods, applications, documents | **Yes (3)** | FORCE RLS |
| `exams` | Types, exams, sessions, enrollments, student_grades | **Yes (5)** | Phase 3A/3B + Phase 7 writers; grades partitioned FORCE RLS |
| `graduation` | Eligibility, awards, evidence, revocations | **Yes (14)** | Phase 3C.12 LIVE — FORCE RLS |
| `certificates` | Templates, versions, certificates, jobs, artifacts | **Yes (6)** | Phase 4.1 LIVE — FORCE RLS |
| `results` | Term/annual results, transcripts | Reserved empty (0) | Physicalization gated |
| `promotion` | Promotion rules/records | Reserved empty | |
| `transfers` | Transfer requests/records | Reserved empty | |
| `documents` | File metadata | Reserved empty | Object storage for blobs |
| `finance` | Fees/payments (later GL) | Reserved empty | |
| `communication` | Templates, messages, jobs | Reserved empty | |
| `workflow` | Approvals | Reserved empty | |
| `security` | RBAC, scopes, security audit | Yes (6) | Users stay in `public` |
| `audit` | Outbox, idempotency; future audit_logs | Partial (2) | |
| `reports` | Materialized reporting views | Partial (3 MVs) | |
| `intelligence` | Monitoring, recommendations, healing audit | Yes (10) | Not in academic 87 |

### Planned schema (D4 — implemented Phase 2)

| Schema | Responsibility | Action |
|--------|----------------|--------|
| `admission` | Application periods, applications, documents | **LIVE** |

---

## B. Conditional ERP schemas (not created yet — D3)

| Schema | Responsibility | Justification gate |
|--------|----------------|--------------------|
| `hr` | Employees, contracts, leave, payroll | Staff ≠ teachers only |
| `inventory` | Items, warehouses, stock, assets | Workshop materials + ERP |
| `facilities` | Maintenance beyond org.rooms | Links inventory/finance/volunteers |
| `medical` | Health records | HIGHLY_RESTRICTED isolation |
| `support` | IEP, accommodations, counseling | Sensitive support services |
| `behavior` | Incidents, merits/demerits | Discipline history |
| `activities` | Clubs, events, assemblies, duties, volunteering | Non-timetable ops |
| `internship` | Partners, placements, logbooks | Vocational summer training |
| `transportation` | Buses, routes, subscriptions | School transport safety |
| `library` | Titles, loans, fines | Optional resource management |
| `alumni` | Post-graduation profiles | After graduation module |
| `analytics` | Extra analytical structures | Only if MVs insufficient |
| `archive` | Cold retention | Lifecycle/retention phase |

### Explicitly not created (unless ADR)

`core`, `identity`, `reference`, `student` (singular), `scheduling` (use `timetable`), `staff` (use `teachers` + `hr`), `optimization` (use `intelligence`), `system`, `integration` (prefer `audit` outbox + app integration).

---

## C. Schema ownership rules

1. Cross-schema FKs are allowed when domains truly integrate (e.g. enrollment → students).  
2. Prefer referencing `organization.schools` and `academic.academic_years` rather than duplicating school/year attributes.  
3. Reporting MVs live in `reports` and are rebuildable.  
4. Intelligence must not own SIS transactional tables.  
5. Renames/merges require migration strategy + human approval before execution (D2).

---

## D. Mapping from master prompt names

| Prompt name | SIS mapping |
|-------------|-------------|
| core | `organization` + `academic` |
| identity | `public.users` + `security` (+ optional future persons) |
| student | `students` |
| academic | `academic` + `curriculum` + `vocational` |
| scheduling | `timetable` |
| assessment | `exams` + `results` |
| staff | `teachers` → later `hr` |
| optimization | `intelligence` |
