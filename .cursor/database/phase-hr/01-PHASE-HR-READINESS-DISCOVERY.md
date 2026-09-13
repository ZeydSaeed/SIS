# PHASE HR — READINESS DISCOVERY (READ-ONLY)

---

```text
Date: 2026-09-13
Mode: READ-ONLY
Status: COMPLETE
Start AuthZ: 00 GRANTED (APPROVED — Phase HR / Phase 9)
```

## A. Live PostgreSQL schemas (2026-09-13)

```text
academic, admission, attendance, audit, certificates, communication,
curriculum, documents, enrollment, exams, finance, graduation, guardians,
intelligence, organization, promotion, public, reports, results, security,
students, teachers, timetable, transfers, vocational, workflow
```

```text
hr schema: ABSENT
payroll tables: ABSENT
employees / job_positions: ABSENT
```

## B. Adjacent LIVE coverage (not HR)

| Area | Status | Notes |
|------|--------|-------|
| `teachers.*` (4) | LIVE | Teaching staff only — Phase 8/8.1 |
| `security.users` / roles | LIVE | Auth identity — not employee master |
| `organization.*` | LIVE | Schools/branches/rooms |
| `finance.*` | LIVE | Student fees path — not payroll GL |
| `documents.files` | LIVE | Metadata + local binary |

## C. Gap vs Master Prompt Phase 9 / §15–16

| Prompt concept | Gap |
|----------------|-----|
| Generalized staff / employees | **MISSING** |
| job_positions / assignments | **MISSING** |
| contracts | **MISSING** |
| leaves / shifts / substitutions | **MISSING** |
| payroll / salary_components / runs | **MISSING** (OUT of Slice-1) |
| Bridge Person SSOT | **NOT opened** — keep users/teachers; no `persons` rewrite |

## D. Conflicts / risks

| Risk | Severity | Posture |
|------|----------|---------|
| Duplicating teachers as employees without bridge | HIGH | Parallel HR + optional `teacher_id` link; no forced migrate |
| Opening payroll with fees ledger | HIGH | Payroll separate AuthZ |
| Renaming teachers.employee_code into HR | MEDIUM | HOLD (Phase 8 debt) |
| Greenfield HR mega-wave | HIGH | Slice-1 catalog only |

## E. Recommended Slice-1 (design ballot)

```text
IN:  hr.job_positions + hr.employees + hr.employee_schools
     FORCE RLS · reject hard DELETE · Register/List HTTP
OUT: contracts, payroll, leaves, shifts, persons rewrite
```

## F. What this discovery does NOT do

```text
No migrations · No tables · No permissions · No HTTP
```
