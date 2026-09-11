# MASTER PHASE 7 — PHASE 7.1 — EXAM ADMINISTRATION — HUMAN SECURITY APPROVAL — HD-001

---

## 1. Document Control

| Field | Value |
|-------|-------|
| **Document Type** | HUMAN SECURITY APPROVAL / DECISION GATE |
| **Date** | 2026-09-11 |
| **Phase** | MASTER PHASE 7 — Assessment / Exams / Grades |
| **Subphase** | PHASE 7.1 — Exam Administration |
| **Decision** | **HD-001** — Exam Administration Security Mapping |
| **Mode** | READ-ONLY HUMAN SECURITY APPROVAL / DECISION GATE ONLY |
| **Implementation Authorization** | **NOT GRANTED** (HD-007 remains separate and ungranted) |

```text
THIS DOCUMENT = security decision gate only
≠ HUMAN IMPLEMENTATION AUTHORIZATION
≠ permission catalog change
≠ role assignment
≠ policy / route / handler / test / UI implementation
≠ conversion of candidates into approvals
```

---

## 2. Authorization Boundary

**Allowed:** inspect repository; create this Markdown artifact only.

**Forbidden (and not performed):**

```text
config/security.php · roles · permissions · policies · middleware · routes
controllers · commands · handlers · DTOs · repositories · services
events · outbox · idempotency · migrations · schema · SQL · RLS
indexes · constraints · tests · jobs · UI
invention of roles / permissions / approvals
resolution of Phase 7.2 security mappings
resolution of query authorization
automatic advancement to HD-007
```

---

## 3. Authority Hierarchy

| Priority | Source | Role |
|----------|--------|------|
| 1 | Master Phase 7 Design Lock (`phase-7/02-…`) | Locked vocabulary; DR-005 / DR-005a |
| 2 | Phase 7.1 Design Decision Lock Amendment (`phase-7.1/10-…`) | HD register; no fabricated approval |
| 3 | Phase 7.1 Human Decision Resolution (`phase-7.1/10A-…`) | **HD-001 = UNRESOLVED** |
| 4 | Phase 7.1 Design Lock Amendment (`phase-7/06-…`) + Gate (`07`) | DR-005 MODIFY — DO NOT AUTO-MAP |
| 5 | CQRS Re-Readiness Audit (`phase-7.1/11-…`) | Security BLOCKED BY HD-001 |
| 6 | Live `config/security.php` + `Permission.php` | Authoritative live catalogs |
| 7 | Policies / middleware / analogous modules | Pattern evidence only |

Older planning docs do not override `10A` / `11` / live catalog evidence.

---

## 4. Locked Phase 7.1 Scope

| Command | Permission (design vocabulary) | In this gate? |
|---------|--------------------------------|---------------|
| CreateExam | `exam.create` | **YES** |
| UpdateExam | `exam.update` | **YES** |
| CancelExam | `exam.cancel` | **YES** |

### Out of scope (explicitly not resolved)

* exam queries / `exam.view` / `exam.read` / `exam.list` / `exam.show`
* exam session commands and permissions (Phase 7.2)
* exam enrollment commands and permissions (Phase 7.2)
* `exam.session.cancel` (forbidden by HD-005; use `exam.session.update` in 7.2)
* grade / attendance / enrollment academic commands
* HTTP route exposure / UI
* automatic role or permission creation
* HD-007 implementation authorization

---

## 5. Critical Permission Rules (Locked)

| Rule | Status |
|------|--------|
| `exam.cancel` is dedicated cancellation vocabulary (DR-005a) | **LOCKED** |
| Do not substitute `exam.update` / `exam.manage` / `exam.write` / wildcards / admin bypass | **LOCKED** |
| No `exam.*` / `*` / `admin.*` / superuser / hardcoded route bypass as substitute | **LOCKED** |
| Existing roles only — no invented Examiner / Exam Officer / Registrar / Assessment Officer | **LOCKED** |
| Candidate ≠ approval | **LOCKED** |

---

## 6. Security Inventory (Repository Evidence)

| Item | Exists? | Source | Status |
|------|---------|--------|--------|
| `exam.create` | **NO** | `config/security.php` permissions list; `Permission.php` constants | **ABSENT** (design vocabulary only) |
| `exam.update` | **NO** | same | **ABSENT** |
| `exam.cancel` | **NO** | same | **ABSENT** |
| Exam role mappings | **NO** | No role lists any `exam.*` permission | **ABSENT** |
| Role catalog | **YES** | `config/security.php` → `roles` | **PROVEN** (no exam admin role) |
| Permission catalog | **YES** | `config/security.php` → `permissions` | **PROVEN** (students/enrollment/grades/attendance/security only) |
| ExamPolicy | **NO** | `app/Security/Policies/**` — Grade/Enrollment/Student/Attendance only | **ABSENT** |
| Exam middleware | **NO** | No exam-specific AuthZ middleware | **ABSENT** |
| Exam admin routes | **NO** | Grade APIs only under exams feature surface | **ABSENT** |
| Wildcard authorization for exam | **NO** | No `exam.*` or `*` exam substitute in catalog | **ABSENT** (correct) |
| Existing analogous mapping | **YES (other modules)** | Grades / Enrollment / Attendance role→permission in `config/security.php` | **INFORMATIVE ONLY** |

```text
Documentation proposals of exam.* vocabulary ≠ live catalog existence.
No inference of existence from Master Lock / 10 / 10A alone.
```

---

## 7. Existing Live Roles (PROVEN)

From `config/security.php`:

| Role | Domain ownership (live permissions) | Exam ownership? |
|------|-------------------------------------|-----------------|
| `student_manager` | students.* | **NONE** |
| `student_viewer` | students.view | **NONE** |
| `enrollment_manager` | enrollment.* + students.view | **NONE** |
| `enrollment_viewer` | enrollment.view + students.view | **NONE** |
| `grades_manager` | grades.* + students.view + enrollment.view | **NONE** |
| `grades_teacher` | grades.view/create + students.view + enrollment.view | **NONE** |
| `grades_viewer` | grades.view + students.view + enrollment.view | **NONE** |
| `attendance_viewer` | attendance.view | **NONE** |
| `attendance_teacher` | attendance session create/mark/close | **NONE** |
| `attendance_manager` | attendance incl. correct + session.cancel | **NONE** |

**PROVEN ABSENT as roles:** Examiner, Exam Officer, Registrar, Assessment Officer, Invigilator, Administrator (as exam role).

`grades_manager` is reported solely as an **EXISTING** role. It is **not** an approved exam administrator.

---

## 8. Analogous Module Security Patterns

| Module | Pattern | Authority for Exam Admin |
|--------|---------|--------------------------|
| **Grades** | Dedicated `grades.*` permissions; `grades_manager` / `grades_teacher` / `grades_viewer`; `GradePolicy` + SchoolAccess | **INFORMATIVE ONLY** — owns grades SoD, not exam lifecycle |
| **Enrollment** | Dedicated `enrollment.create/update/cancel`; `enrollment_manager` | **INFORMATIVE ONLY** — academic enrollment ≠ exam seat admin |
| **Attendance** | Dedicated cancel `attendance.session.cancel`; manager vs teacher split | **INFORMATIVE ONLY** — analogy for dedicated cancel vocab, not role ownership |
| **Graduation** | `config/sis.php` graduation authority lists; not mirrored as `exam.*` in `security.php` | **INSUFFICIENT / NOT AUTHORITATIVE** for exam mapping |
| **Students** | `student_manager` / viewer | **CONFLICTING if reused** for exam cancel power |

```text
Analogy classification: INFORMATIVE ONLY.
Do not copy Grades/Enrollment/Attendance mappings automatically to exam.*.
Exam Administration does NOT inherit grades_manager ownership by analogy.
```

---

## 9. Role / Permission Decision Matrix

| Permission | Candidate Existing Roles | Evidence | Human Decision | Implementation Status |
|------------|--------------------------|----------|----------------|-----------------------|
| `exam.create` | `grades_manager` (candidate only) | Role **EXISTS** in `config/security.php`; mapping proposed in `10`/`10A`/`09` as candidate; **no** live permission; **no** approved mapping artifact | **UNRESOLVED** | **NOT IMPLEMENTED** |
| `exam.update` | `grades_manager` (candidate only) | Same | **UNRESOLVED** | **NOT IMPLEMENTED** |
| `exam.cancel` | `grades_manager` (candidate only) | Same; dedicated cancel vocab locked (DR-005a); role ownership still open | **UNRESOLVED** | **NOT IMPLEMENTED** |

### Candidate detail rules applied

1. Role existence for `grades_manager`: **PROVEN** (`config/security.php`).
2. Source of candidate mapping: design docs `09` / `10` / `10A` — **proposal / candidate**, not approval.
3. Existing role ≠ proposed mapping ≠ approved mapping.
4. No cell marked **APPROVED** — no explicit human approval exists in repository.

### Explicitly not candidates for silent approval

| Role | Why not auto-mapped |
|------|---------------------|
| `grades_teacher` | Write-limited grades; over-broad for exam cancel; under-fit for admin lifecycle |
| `grades_viewer` | View-only |
| `attendance_manager` | Different BC; cancel semantics differ |
| `enrollment_manager` | Academic enrollment ≠ exam administration |
| Invented Examiner / Registrar / Exam Officer | Forbidden / nonexistent |

---

## 10. Explicit Human Decisions Found

| Decision | Source | Finding |
|----------|--------|---------|
| HD-001 status | `10A` §5 / §17 | **UNRESOLVED — HUMAN APPROVAL REQUIRED** |
| Do not auto-map `exam.*` | Master Lock DR-005; `06` §4.5; `10` §7 | **PROVEN** — forbid auto-map |
| Dedicated `exam.cancel` vocabulary | DR-005a | **APPROVED** (vocabulary only — **not** role ownership) |
| `grades_manager → exam.*` | `10A` | **CANDIDATE / NOT APPROVED** |
| HD-002 Commands only | `10A` | **APPROVED** — no query AuthZ in this gate |
| HD-005 `exam.session.update` | `10A` | **APPROVED** for Phase **7.2** only — not resolved here |
| HD-007 Implementation auth | `10A` / `11` | **NOT GRANTED** |
| Audit `11` security status | Re-readiness | **BLOCKED BY HD-001** |

```text
NO repository artifact contains an explicit authoritative human APPROVAL
of any role → exam.create / exam.update / exam.cancel mapping.
```

---

## 11. HUMAN DECISION — HD-001

### Decision

**UNRESOLVED**

### Approved mappings

| Permission | Role |
|------------|------|
| `exam.create` | — **NONE APPROVED** |
| `exam.update` | — **NONE APPROVED** |
| `exam.cancel` | — **NONE APPROVED** |

### Rejected mappings

| Permission | Role | Reason |
|------------|------|--------|
| — | — | No explicit human **REJECTED** decisions recorded for specific role mappings in this gate. Auto-map of all `exam.*` to grades/attendance/enrollment remains **forbidden by lock**, which is a process prohibition — not a per-row REJECTED mapping table entry. |

### Unresolved mappings

| Permission | Candidate Role | Reason |
|------------|----------------|--------|
| `exam.create` | `grades_manager` | Role exists; mapping is candidate-only; no explicit human APPROVED artifact |
| `exam.update` | `grades_manager` | Same |
| `exam.cancel` | `grades_manager` | Same; cancel is high-impact and must not be inferred from grades SoD |
| `exam.create` | (any other existing role) | No approved alternative; insufficient evidence |
| `exam.update` | (any other existing role) | No approved alternative; insufficient evidence |
| `exam.cancel` | (any other existing role) | No approved alternative; insufficient evidence |

```text
HD-001 = UNRESOLVED — HUMAN SECURITY APPROVAL REQUIRED
Fail-closed: no mapping may be treated as approved.
```

---

## 12. Security Design vs Implementation Authorization

| Concern | Gate | Status |
|---------|------|--------|
| Security mapping approval | **HD-001** (this document) | **UNRESOLVED** |
| Implementation authorization | **HD-007** (separate) | **NOT GRANTED** |

```text
Even IF HD-001 later becomes APPROVED,
implementation remains blocked until a separate
Phase 7.1 Implementation Authorization Gate grants HD-007.

HD-001 approval ≠ implementation authorization.
HD-001 approval ≠ HTTP exposure authorization by itself
without catalog/policy/route work under HD-007.
```

Current HTTP status remains **BLOCKED**.

---

## 13. Phase 7.2 / Query Boundaries Preserved

| Item | Treatment in this gate |
|------|------------------------|
| `exam.session.*` / `exam.enrollment.*` | **NOT RESOLVED** |
| `exam.session.cancel` | **NOT INTRODUCED** (HD-005: use `exam.session.update` in 7.2) |
| `exam.view` / query permissions | **NOT INTRODUCED** (HD-002 / HD-003) |
| Phase 7.2 artifacts | **NOT MODIFIED** |

---

## 14. Security Acceptance Criteria

* [x] `exam.create` has explicit human-approved role mapping **OR remains UNRESOLVED** → **UNRESOLVED**
* [x] `exam.update` has explicit human-approved role mapping **OR remains UNRESOLVED** → **UNRESOLVED**
* [x] `exam.cancel` has explicit human-approved role mapping **OR remains UNRESOLVED** → **UNRESOLVED**
* [x] `exam.cancel` remains a dedicated permission (vocabulary) → **YES**
* [x] no wildcard permission used as substitute → **YES**
* [x] no invented role in the decision → **YES**
* [x] no Phase 7.2 permission resolved → **YES**
* [x] no query authorization resolved → **YES**
* [x] implementation authorization not inferred → **YES** (HD-007 NOT GRANTED)
* [x] no code/config/schema changes occurred → **YES**

---

## 15. Workspace / File Safety

| Observation | Classification |
|-------------|----------------|
| This task created only `12-PHASE-7.1-EXAM-ADMINISTRATION-HUMAN-SECURITY-APPROVAL-HD-001.md` | **ALLOWED** |
| Pre-existing `M` on `phase-7/02-MASTER-PHASE-7-DESIGN-LOCK.md` | **NOT repaired / NOT overwritten** |
| Pre-existing untracked phase-7 / phase-7.1 docs | **NOT touched** |
| `config/security.php` / PHP / migrations / routes / tests | **NOT modified** |

```text
FILE SAFETY PASS for this task scope.
No unauthorized files changed by this gate.
```

---

## 16. Final Verdict

```text
MASTER PHASE 7 — PHASE 7.1
HUMAN SECURITY APPROVAL — HD-001

Security Mapping: UNRESOLVED
Implementation Authorization: NOT GRANTED
HTTP Exposure: BLOCKED

HD-001: UNRESOLVED — HUMAN SECURITY APPROVAL REQUIRED

No implementation is authorized.

Next Gate:
PHASE 7.1 — HUMAN SECURITY APPROVAL DECISION
```

```text
Do NOT advance to HD-007 automatically.
Do NOT modify the permission catalog.
Do NOT assign roles.
Do NOT invent Examiner / Registrar / Exam Officer.
Do NOT treat grades_manager → exam.* as approved.
```
