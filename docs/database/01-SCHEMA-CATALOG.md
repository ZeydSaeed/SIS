# 01 — Schema Catalog

**Status:** Phase 1 (ADR-020 locked)  
**Rule:** Create a schema only with documented responsibility. Empty reserved schemas already created for blueprint modules may remain until tables land.  
**D2:** Do not rename live schemas for cosmetic alignment with external naming lists.

---

## A. Live schemas (implemented or reserved)

| Schema | Responsibility | Tables now? | Notes |
|--------|----------------|-------------|-------|
| `public` | Laravel auth, cache, queues, migrations | Yes | Framework; not SIS domain |
| `organization` | Ministry → school → branch → department → room | Yes | `branches` = campuses (D2) |
| `academic` | Years, terms, grade levels, holidays, settings | Yes | Cross-cutting academic calendar |
| `vocational` | Specializations, tracks, subject links | Yes | Vocational structure |
| `students` | Student master + contacts/addresses | Yes | Missing documents table |
| `guardians` | Guardians and student links | Yes | |
| `enrollment` | Classes, sections, enrollments, subject links | Yes | RLS on enrollments |
| `teachers` | Teacher profiles and assignments | Yes | Not full HR |
| `curriculum` | Subjects, curricula, links | Yes | Missing prerequisites |
| `timetable` | Periods and (future) schedules | Partial | Only periods |
| `attendance` | Sessions, records, daily summary | Yes | Partitioned records |
| `admission` | Application periods, applications, documents | **Yes (Phase 2)** | No duplicate student identity; RLS fail-closed |
| `results` | Term/annual results, transcripts | Reserved empty | D5 |
| `promotion` | Promotion rules/records | Reserved empty | |
| `transfers` | Transfer requests/records | Reserved empty | |
| `graduation` | Eligibility and records | Reserved empty | |
| `certificates` | Templates, issuance, jobs | Reserved empty | |
| `documents` | File metadata | Reserved empty | Object storage for blobs |
| `finance` | Fees/payments (later GL) | Reserved empty | |
| `communication` | Templates, messages, jobs | Reserved empty | |
| `workflow` | Approvals | Reserved empty | |
| `security` | RBAC, scopes, security audit | Partial | Users stay in `public` |
| `audit` | Outbox, idempotency; future audit_logs | Partial | |
| `reports` | Materialized reporting views | Partial (3 MVs) | |
| `intelligence` | Monitoring, recommendations, healing audit | Yes | Not in academic 87 |

### Planned schema (D4 — implemented Phase 2)

| Schema | Responsibility | Action |
|--------|----------------|--------|
| `admission` | Application periods, applications, documents | **LIVE** — SchemaHelper + migrations 2026_09_10_131* |

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
