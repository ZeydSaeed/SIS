# PHASE 3C.11 — DENORMALIZED IDENTITY PLAN

**Triggers NOT created.**

## Principles

- Strongest DB mechanism preferred.  
- Application validation necessary but insufficient for cross-tenant integrity.  
- Independent `enrollment_id` FK without `school_id` **forbidden** on enrollment-scoped roots.

---

## Field matrix

| Field | Source of truth | Why denorm | Invariant | Enforcement | Failure |
|-------|-----------------|------------|-----------|-------------|---------|
| school_id | Tenant context + parent | RLS + listing | Matches SchoolContext on write; matches enrollment.school_id | Composite FK + RLS WITH CHECK | Insert/update rejected |
| enrollment_id | enrollment.enrollments | Grain HD-39 | Exists in school | Composite FK `(enrollment_id, school_id)` | FK violation |
| student_id | enrollment.student_id | Query without join | Equals enrollment.student_id | **Trigger** BEFORE INSERT/UPDATE OR composite unique parent if available | RAISE |
| academic_year_id | enrollment.academic_year_id | Year boards | Equals enrollment.academic_year_id | Trigger and/or composite FK `(enrollment_id, academic_year_id)` (LIVE unique exists from grades) | RAISE / FK |
| specialization_id | enrollment.specialization_id | Program context | Equals enrollment (NULL↔NULL) | Trigger | RAISE |

### Decision: trigger vs FK

| Mechanism | Use when |
|-----------|----------|
| Composite FK `(enrollment_id, school_id)` | Always on outcomes, awards, approvals |
| Composite FK `(enrollment_id, academic_year_id)` | Prefer if denorm year present (LIVE support exists) |
| Trigger compare-to-enrollment | student_id / specialization_id when no composite unique parent |
| CHECK alone | Insufficient for cross-table equality |
| Generated column | Not used — would still need enrollment join |
| Application only | **Rejected** for denorm integrity |

### Child tables (evaluations, evidence)

| Field | Plan |
|-------|------|
| school_id denorm | Must equal parent version/set school_id — trigger or set only via parent join in repository **plus** trigger |

### Transaction boundary

Denorm copy occurs in same INSERT transaction as parent row creation; trigger fires in-statement.

### RLS interaction

WITH CHECK ensures session school matches row school_id; composite FK ensures enrollment belongs to that school — defense in depth.

### Test strategy

- Insert outcome with wrong student_id → fail  
- Insert with enrollment from other school → FK fail  
- Insert with mismatched academic_year_id → fail  
- Cross-school GUC → RLS fail  
