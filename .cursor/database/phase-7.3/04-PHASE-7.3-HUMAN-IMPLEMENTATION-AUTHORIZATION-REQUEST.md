# MASTER PHASE 7 — PHASE 7.3
# HUMAN IMPLEMENTATION AUTHORIZATION REQUEST

---

```text
Document Type:
HUMAN IMPLEMENTATION AUTHORIZATION REQUEST

Subphase:
PHASE 7.3 — GRADE HARDENING / POLICY COMPLETION

Design Lock:
03-PHASE-7.3-DESIGN-LOCK.md — LOCKED

This document is NOT authorization.
Until explicit human approval:

PHASE 7.3 IMPLEMENTATION = NOT AUTHORIZED
```

| Field | Value |
|-------|-------|
| **Date** | 2026-09-12 |
| **Units requested** | **7.3-U01** + **7.3-U02** (may approve one or both) |
| **Design Lock** | `03` |
| **Ballot** | `02` RECOMMENDED SET (001=A) |

---

## 1. Requested Scope

### 7.3-U01 — Grade idempotency enforcement

```text
AUTHORIZED WORK IF GRANTED:
  - Require non-empty idempotencyKey on Enter/Correct/Void/Finalize commands
  - Align HTTP GradeController mutating actions to fail-closed without key
    (Idempotency-Key header or documented body field — match existing API convention)
  - Harden Feature tests (missing key, replay, conflict)
  - Verify same-COMMIT outbox/idempotency posture (no schema redesign)

FORBIDDEN:
  - New grade routes
  - Optional-key backdoor
  - audit.idempotency_keys redesign
  - DB/RLS permission changes
```

### 7.3-U02 — Academic-year partition ensure

```text
AUTHORIZED WORK IF GRANTED:
  - Ensure academic-year creation creates student_grades LIST partition for that year
  - Preserve fail-closed on missing partition
  - FORBIDDEN: DEFAULT partition

FORBIDDEN:
  - Dropping partitions
  - Disabling partition checks
  - Attendance partition redesign
```

---

## 2. Human Ballot

```text
[x] APPROVE 7.3-U01 ONLY (idempotency)
[ ] APPROVE 7.3-U02 ONLY (partition ensure)
[ ] APPROVE 7.3-U01 + 7.3-U02 (both)
[ ] REJECT
```

```text
Approver: HUMAN (explicit chat)
Date: 2026-09-12
Notes: APPROVE 7.3-U01 ONLY — U02 remains NOT AUTHORIZED
```

```text
HTTP idempotency transport:
[x] Prefer Idempotency-Key header (fail-closed if missing)
    Implemented as existing X-Idempotency-Key (GradeController)
```

```text
7.3-U01 IMPLEMENTATION AUTHORIZATION: GRANTED (2026-09-12)
7.3-U02: NOT AUTHORIZED
```

---

## 3. STOP (request record)

```text
U01 AuthZ GRANTED — see implementation audit 05
U02 remains pending separate approval
```
