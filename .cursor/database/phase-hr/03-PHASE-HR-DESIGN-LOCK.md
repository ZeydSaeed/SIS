# PHASE HR — DESIGN LOCK (Slice-1 employees foundation)

---

```text
Status: LOCKED — Slice-1 IMPLEMENTATION CLOSED WITH CONDITIONS (see 05/06)
Date: 2026-09-13
Slice: HR-EMPLOYEES-FOUNDATION
Ballot: 02 LOCKED
Start AuthZ: 00 GRANTED
Implementation: HR-U01+U02 CLOSED WITH CONDITIONS — payroll OUT
```

## In (Slice-1)

### `hr.job_positions`

```text
id, school_id?, code, name, category (SMALLINT), status, created_at, updated_at
— school_id: ADD for RLS (tenant job catalogs); UNIQUE(school_id, code)
```

### `hr.employees`

```text
id, employee_number (UNIQUE), user_id?, teacher_id? (UNIQUE nullable),
national_id?, first_name, last_name, full_name,
hire_date?, status (Active/Inactive),
effective_from, effective_to?,
created_at, updated_at
— No school_id on body (membership via employee_schools) — mirror teachers body RLS pattern
```

### `hr.employee_schools`

```text
id, employee_id, school_id, academic_year_id, job_position_id?, is_primary, created_at
FORCE RLS on school_id
```

### Application / HTTP

```text
- RegisterEmployee (creates employee + primary employee_schools)
- ListEmployees (school-scoped)
- CreateJobPosition / ListJobPositions
- Permissions: hr.view, hr.manage
- Idempotency on writes
```

## Out (explicit)

```text
- payroll_runs / salary_components / payslips
- contracts / leave_requests / shifts / substitutions
- Migrating all teachers into employees automatically
- persons SSOT rewrite
- ON DELETE CASCADE of history
```

## Invariants

| ID | Rule |
|----|------|
| INV-HR-01 | Teachers remain teaching SSOT; HR is generalized staff |
| INV-HR-02 | Optional `teacher_id` link only — no forced 1:1 |
| INV-HR-03 | No hard DELETE of employees |
| INV-HR-04 | School ops via employee_schools + FORCE RLS |
| INV-HR-05 | Payroll never auto-opens from this lock |
| INV-HR-06 | BIGINT IDENTITY PKs |

## Units (planned)

| Unit | Name | Status |
|------|------|--------|
| HR-U01 | Schema job_positions + employees + employee_schools + RLS | **CLOSED WITH CONDITIONS** |
| HR-U02 | Register/List Employee + Position HTTP | **CLOSED WITH CONDITIONS** |
| HR-U03 | Slice-1 closure gate | **CLOSED WITH CONDITIONS** (06) |
| HR-PAY | Payroll | **NOT AUTHORIZED** |

## Blueprint impact (when U01 runs)

```text
New schema: hr
+3 tables → blueprint object count 91 → 94 (estimated)
```

---

```text
HUMAN APPROVAL REQUIRED for HR-U01 implementation
Reply: APPROVED — HR-U01
(or «استمر» if you grant U01 under continuation authority)
```

**STOP — no migrations / HTTP until U01 AuthZ.**
