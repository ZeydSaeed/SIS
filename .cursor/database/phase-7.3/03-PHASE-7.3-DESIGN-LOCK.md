# MASTER PHASE 7 — PHASE 7.3
# DESIGN LOCK

---

```text
Document Type:
PHASE 7.3 DESIGN LOCK

Status:
LOCKED — pending Implementation Authorization before code

Date:
2026-09-12

Ballot:
02 — RECOMMENDED SET RECORDED (001=A)

Start AuthZ:
00 — APPROVED

Parent:
phase-7/02-MASTER-PHASE-7-DESIGN-LOCK.md
```

```text
Design Lock ≠ Implementation Authorization
```

---

## 1. In Scope (LOCKED)

| WP | Decision | Behavior |
|----|----------|----------|
| **WP-73-01** | HD-7.3-001 = **A** | Enter / Correct / Void / Finalize **require** non-empty `idempotencyKey`; empty/null → fail-closed |
| **WP-73-02** | implied by 001 | Same-COMMIT idempotency + outbox posture retained/verified; no `audit.idempotency_keys` redesign |
| **WP-73-03** | HD-7.3-005 = **A** | Academic-year creation path must ensure `student_grades` year partition exists; **no DEFAULT** partition; fail-closed preserved |
| **WP-73-07** | HD-7.3-008 = **A** | Additional Feature/security negative tests for missing idempotency key + AuthZ |

HTTP GradeController mutating endpoints must supply/fail on missing idempotency keys (comply with WP-73-01). No new routes.

---

## 2. Explicitly Out of Scope / Deferred (LOCKED)

| Item | Decision |
|------|----------|
| P7-D7 date-window | **DEFERRED** (HD-7.3-002 = B) |
| Submitted workflow | **DEFERRED** (HD-7.3-003 = B) |
| Excused/Withheld/Incomplete | **DEFERRED** (HD-7.3-004 = B) |
| Phase 7.2 Open/Close/Update/Present RLS writer proofs | **EXCLUDED** (HD-7.3-006 = B) |
| New HTTP exposure / new grade routes | **OUT OF SCOPE** (HD-7.3-007 = B) |
| Results / GPA / Transcript tables | **NOT 7.3** (7.4/7.5) |
| `exam.session.cancel` | **FORBIDDEN** — do not touch |
| DEFAULT partition on `student_grades` | **FORBIDDEN** |
| Hard-delete grades | **FORBIDDEN** |
| Store letter/GPA/credits on `student_grades` | **FORBIDDEN** (P7-D6) |

---

## 3. Security / Integrity Rules

```text
1. Idempotency key required on Grade mutating commands + HTTP writers that invoke them
2. Fingerprint conflict → fail-closed (existing pattern)
3. Partition missing → fail-closed (existing)
4. Year-create partition ensure → no DEFAULT; LIST by academic_year_id only
5. No RLS policy mutation unless separately authorized (not in this lock)
6. No permission catalog expansion in 7.3 unless separately authorized
```

---

## 4. Implementation Units (proposed)

| Unit | WP | Name |
|------|-----|------|
| **7.3-U01** | WP-73-01/02/07 | Grade mutating idempotency enforcement + tests (+ HTTP request compliance) |
| **7.3-U02** | WP-73-03 | Academic-year → student_grades partition ensure (no DEFAULT) |

```text
Units require separate HUMAN IMPLEMENTATION AUTHORIZATION before coding.
May be authorized together or sequentially.
```

---

## 5. Acceptance Criteria (for later audit)

### U01

- Enter/Correct/Void/Finalize reject null/empty idempotency keys
- HTTP grade store/correct/void/finalize reject missing key (or always pass server-generated key — **prefer client/header required fail-closed**; document choice in AuthZ request)
- Replay with same key returns prior result; conflict fails closed
- security:validate / Feature tests green

### U02

- Creating academic year ensures matching grades partition exists
- Missing partition still fail-closed on write
- No DEFAULT partition created
- Evidence via test or verified ops path

---

## 6. Residual Conditions (document; non-blocking for lock)

```text
P7-D7 date-window: DEFERRED
Submitted workflow: DEFERRED
Vocab Excused/Withheld/Incomplete: DEFERRED
Phase 7.2 RLS writer gaps: NON-BLOCKING (excluded)
```

---

## 7. STOP

```text
PHASE 7.3 DESIGN LOCK: LOCKED

Implementation: NOT AUTHORIZED

Next:
04-PHASE-7.3-HUMAN-IMPLEMENTATION-AUTHORIZATION-REQUEST.md

STOP — WAIT FOR HUMAN IMPLEMENTATION APPROVAL
```
