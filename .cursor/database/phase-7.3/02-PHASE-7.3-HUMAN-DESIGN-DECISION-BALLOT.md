# MASTER PHASE 7 — PHASE 7.3
# HUMAN DESIGN DECISION BALLOT

---

```text
Document Type:
HUMAN DESIGN DECISION BALLOT → RECORDED

Subphase:
PHASE 7.3 — GRADE HARDENING / POLICY COMPLETION

Date:
2026-09-12

Start AuthZ:
APPROVED (00)

Human continuation:
“استمر” — apply RECOMMENDED SET

HD-7.3-001 selection:
A — HTTP grade writers already exist (routes/api.php GradeController store/correct/void/finalize)

Implementation:
NOT AUTHORIZED until Design Lock + Implementation AuthZ
```

---

## Recorded Decisions

### HD-7.3-001 — Grade idempotency enforcement (P7-D5)

```text
[x] A — REQUIRE non-empty idempotency key on all Grade mutating commands (fail-closed if missing)
[ ] B
[ ] C
```

**Rationale:** Grade HTTP mutating routes are live; P7-D5 applies.

### HD-7.3-002 — Enrollment date-window (P7-D7)

```text
[ ] A
[x] B — KEEP DEFERRED — no date-window in 7.3; document residual explicitly
[ ] C
```

### HD-7.3-003 — Submitted workflow

```text
[ ] A
[x] B — KEEP DEFERRED — enum remains reserved; no Submit command
[ ] C
```

### HD-7.3-004 — Vocabulary Excused / Withheld / Incomplete

```text
[ ] A
[x] B — KEEP DEFERRED — do not expand grade outcomes in 7.3
```

### HD-7.3-005 — Partition year-create automation (P7-D9)

```text
[x] A — AUTHORIZE design for academic-year creation → ensure student_grades partition (no DEFAULT)
[ ] B
[ ] C
```

### HD-7.3-006 — Phase 7.2 residual RLS writer proofs

```text
[ ] A
[x] B — EXCLUDE from 7.3 — remain NON-BLOCKING Phase 7.2 conditions
```

### HD-7.3-007 — HTTP grade writer exposure

```text
[ ] A
[x] B — OUT OF SCOPE for 7.3 as *new* exposure work — existing HTTP must honor HD-7.3-001 keys
```

**Clarification:** B means do not expand HTTP surface; existing GradeController must comply with required idempotency keys.

### HD-7.3-008 — Additional AuthZ / negative security tests

```text
[x] A — INCLUDE hardening tests WP
[ ] B
```

---

## Consolidated stamp

```text
[x] APPROVE RECOMMENDED SET
    HD-7.3-001 = A
    HD-7.3-002 = B
    HD-7.3-003 = B
    HD-7.3-004 = B
    HD-7.3-005 = A
    HD-7.3-006 = B
    HD-7.3-007 = B
    HD-7.3-008 = A

Approver: HUMAN (استمر → recommended set)
Date: 2026-09-12
```

---

## STOP (ballot)

```text
DESIGN DECISIONS: RECORDED
Next: Design Lock (03) → Implementation Authorization Request (04)
```
