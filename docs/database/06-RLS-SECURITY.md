# 06 — RLS Security

**Status:** Phase 1 design (no new RLS enabled in this phase)  
**Related:** ADR-005 (+ D6 amendment), ADR-020 D6

---

## Policy

Row-Level Security is **defense in depth**, not a substitute for Laravel Policies.

Expansion is **incremental**:

1. Identify school-scoped sensitive table  
2. Confirm `school_id` (or equivalent) column and indexes  
3. Design policy (fail-closed)  
4. Ensure HTTP + queue/console set `app.current_school_id`  
5. Enable RLS in additive migration  
6. Test cross-school denial  
7. Gate + human review  

**Do not** enable RLS blindly on every table.  
**Do not** disable RLS without explicit human approval.

---

## Live baseline

| Table | RLS | FORCE | Policy |
|-------|-----|-------|--------|
| `enrollment.enrollments` | ON | OFF | `enrollment_school_isolation` (fail-closed) |
| `attendance.records` | ON | OFF | `attendance_school_isolation` (fail-closed) |
| `admission.application_periods` | ON | **ON** | `admission_periods_school_isolation` (fail-closed) |
| `admission.applications` | ON | **ON** | `admission_applications_school_isolation` (via period.school_id) |
| `admission.application_documents` | ON | **ON** | `admission_documents_school_isolation` (via application→period) |

GUC: `app.current_school_id` (BIGINT as text).

---

## Priority review order (D6)

1. students  
2. enrollments *(done)*  
3. attendance *(records done; review sessions/summary)*  
4. assessment/grades  
5. staff/HR  
6. finance  
7. medical  
8. behavior  
9. documents  
10. support/SEN  
11. inventory (where school-scoped)  
12. communications (where school-scoped)  

---

## Design template (per table)

```text
Table:
school_id column: yes/no
Classification: CONFIDENTIAL / RESTRICTED / HIGHLY_RESTRICTED
Policy name:
USING expression:
WITH CHECK expression:
FORCE ROW LEVEL SECURITY: yes/no (recommended yes for tenant tables)
Bypass roles: migrations only (document)
App context setters: middleware + queue
Negative tests: cross-school SELECT/UPDATE denied
```

---

## HIGHLY_RESTRICTED domains

Medical / SEN require role claims beyond school_id (e.g. `app.can_access_medical`). Ordinary teachers must not receive default access.

---

## Phase 1 deliverable

Design only. Implementation migrations belong to later gated phases.
