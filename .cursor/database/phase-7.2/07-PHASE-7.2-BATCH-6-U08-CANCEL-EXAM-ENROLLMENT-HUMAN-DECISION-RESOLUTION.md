# MASTER PHASE 7 — PHASE 7.2 — BATCH 6 — U08 CANCEL EXAM ENROLLMENT — HUMAN DECISION RESOLUTION

---

## 1. Header / Document Control

| Field | Value |
|-------|-------|
| **Title** | PHASE 7.2 — Batch 6 — U08 CancelExamEnrollment — Human Decision Resolution |
| **Phase** | MASTER PHASE 7 — Assessment / Exams / Grades |
| **Subphase** | PHASE 7.2 — Exam Session / Exam Enrollment Lifecycle |
| **Batch** | BATCH 6 |
| **Unit** | **U08 — CancelExamEnrollment** |
| **Document Type** | HUMAN DECISION RESOLUTION / DESIGN AMENDMENT PREPARATION ONLY |
| **Date** | 2026-09-12 |
| **Mode** | DISCOVER → RECONCILE → DOCUMENT → STOP |
| **Predecessor readiness audit** | MASTER PHASE 7 — PHASE 7.2 — BATCH 6 — U08 READINESS AUDIT (verdict: **BLOCKED**) |
| **Design Lock** | `.cursor/database/phase-7.2/04-PHASE-7.2-DESIGN-LOCK.md` |
| **Prior resolution package** | `.cursor/database/phase-7.2/03-PHASE-7.2-HUMAN-DECISION-RESOLUTION.md` |
| **Ballot / Approval** | `.cursor/database/phase-7.2/04A-*.md`, `04B-*.md` |
| **Gate** | `.cursor/database/phase-7.2/05-PHASE-7.2-IMPLEMENTATION-AUTHORIZATION-GATE.md` |
| **Phase AuthZ record** | `.cursor/database/phase-7.2/06-PHASE-7.2-HUMAN-IMPLEMENTATION-AUTHORIZATION-RECORD.md` |

```text
THIS DOCUMENT = U08 blocking-decision resolution ballot / preparation only
≠ Design Lock amendment executed
≠ Implementation Authorization
≠ Batch 6 authorization
≠ U08 implementation authorization
≠ permission / role / RLS / migration / application / HTTP change
≠ auto-approval of recommendations
```

**Rule:** A recommendation is not a decision. A proposal is not an approval. Only explicit human approval may move HD-U08-001/002/003 to APPROVED / LOCKED.

---

## 2. Authorization State

```text
Phase Authorization ≠ Batch Authorization ≠ Unit Authorization
```

| State | Value |
|-------|-------|
| MASTER PHASE 7 | ACTIVE (design program) |
| PHASE 7.2 Design Lock | APPROVED / LOCKED |
| PHASE 7.2 framework AuthZ (`06`) | APPROVED (framework scope only) |
| **Batch 6** | **NOT AUTHORIZED** |
| **U08** | **NOT AUTHORIZED** |
| **Implementation** | **NOT AUTHORIZED** |
| U09–U15 | **NOT AUTHORIZED** |
| HTTP | **NOT AUTHORIZED** |
| DB / RLS / permission mutation (this task) | **NOT AUTHORIZED** |

```text
The Phase 7.2 Authorization Record (06) does NOT authorize U08.
Gate matrix row 7.2-U08 remains NOT AUTHORIZED until explicit Batch 6 / U08 unit AuthZ.
This artifact does NOT create Batch 6 or U08 implementation authorization.
```

---

## 3. Baseline Locked Decisions (U08 — Preserve)

The following remain **LOCKED** and must not be reinterpreted by this ballot:

| Topic | Locked rule | Source |
|-------|-------------|--------|
| Command | `CancelExamEnrollment` | Design Lock §7; Gate `7.2-U08` |
| Domain event | `ExamEnrollmentCancelled` (reuse class) | Design Lock §19; Master Lock §9.3 |
| Event cause (U08) | `exam_enrollment_cancel` | Design Lock §19; Gate U08 |
| Permission | `exam.enrollment.cancel` | Design Lock; HD-7.2-001; U16 catalog |
| Role | `grades_manager` | HD-7.2-001 / U16 |
| Allowed transitions | **Registered → Withdrawn** | Design Lock §7 `*active*` |
| | **Confirmed → Withdrawn** | Design Lock §7 `*active*` |
| | **Present → Withdrawn** | Design Lock §7 `*active*` (Present ∈ active) |
| Active definition | Registered \| Confirmed \| Present | Design Lock §7; `isActiveSeat` |
| CURRENT grade | **FAIL CLOSED** | DR-002; Design Lock §8 |
| Historical grade | Does **not** independently block | Master Lock §9.3 DR-002 |
| Grade mutation | **FORBIDDEN** | DR-002 |
| Attendance mutation | **FORBIDDEN** | Design Lock Present separation / Phase 7.2 scope |
| HTTP | **OUT OF SCOPE** | Design Lock §23 |
| Database migration | **NONE** | Gate / Design Lock |
| RLS / security infra change | **NONE** for U08 design | DD-019 |
| Transaction | same-COMMIT mutation + outbox + idempotency | Design Lock §20 |
| Event identity ≠ idempotency identity | **LOCKED** | DR-006 |
| U05 cascade | Session cancel withdraws **active** seats; cause `exam_session_cancel` | HD-7.2-002B; U05 impl |
| U07 | **MUST NOT** provide `* → Withdrawn` | Design Lock; U07 guard |
| Restore | Absent \| Withdrawn → active **FORBIDDEN** | Design Lock §7; DD-011 |

---

## 4. Blocking Decisions

| ID | Topic | Status in this artifact |
|----|-------|-------------------------|
| **HD-U08-001** | Already Withdrawn / repeat CancelExamEnrollment | **HUMAN APPROVED = A** (see `08` + DL-7.2-U08-001) |
| **HD-U08-002** | Absent → Withdrawn | **HUMAN APPROVED = A** (see `08` + DL-7.2-U08-001) |
| **HD-U08-003** | Cancelled session × U08 | **HUMAN APPROVED = B** (see `08` + DL-7.2-U08-001) |
| **HD-U08-004** | Idempotency fingerprint field set | **RESOLVED BY EXISTING LOCKED PATTERN** (non-blocking) |

Until HD-U08-001, HD-U08-002, and HD-U08-003 are **formally approved** by human decision:

```text
U08 = BLOCKED
Batch 6 = NOT AUTHORIZED
U09 = NOT AUTHORIZED
```

**Update (2026-09-12):** HD-U08-001/002/003 are now **HUMAN APPROVED** and encoded in Design Lock revision **DL-7.2-U08-001** (see `08-…APPROVAL-RECORD.md`). Blocking design decisions for U08 are closed. **Batch 6 / U08 implementation remain NOT AUTHORIZED** pending readiness re-audit + separate unit AuthZ.

---

## 5. Evidence Matrix

### HD-U08-001 — Already Withdrawn / Repeat Cancel

| Field | Content |
|-------|---------|
| **Source** | Design Lock §8 CancelExamEnrollment; HD-7.2-002A; Master Lock §9.3 |
| **Section** | Session cancel A1 vs enrollment cancel silence |
| **Existing rule** | HD-7.2-002A: **session** already Cancelled → idempotent no-op. **No** parallel rule for enrollment already Withdrawn. |
| **Explicit or silent** | **SILENT** for U08 |
| **Conflict / silence** | **SILENCE** — not a conflict with U05; gap relative to session cancel pattern |
| **U05 evidence** | `ExamSessionCancelGuard` returns already-Cancelled as no-op; no second session event on no-op path |
| **U07 evidence** | N/A (no withdraw path) |

### HD-U08-002 — Absent → Withdrawn

| Field | Content |
|-------|---------|
| **Source** | Design Lock §7 transition table; Master Lock §10.3; U05 `withdrawActiveEnrollmentsForSession` |
| **Section** | `*active*` → Withdrawn only |
| **Existing rule** | Allowed Withdrawn transitions listed for **active** seats only. Absent is **not** active. |
| **Explicit or silent** | **SILENT** on Absent→Withdrawn (not listed as allowed) |
| **Conflict / silence** | **SILENCE** — expanding matrix would be unauthorized without amendment |
| **U05 evidence** | Cascade selects Registered\|Confirmed\|Present only — **Absent survives** session cancel |
| **U07 evidence** | Can set Absent; cannot withdraw |

### HD-U08-003 — Cancelled Session × U08

| Field | Content |
|-------|---------|
| **Source** | Design Lock §8 CancelExamEnrollment; Create/Update enrollment Cancelled rules; HD-B5-002 (U07) |
| **Section** | Session-status preconditions for cancel enrollment |
| **Existing rule** | U06: session Cancelled → create fail closed. U07: any mutation on Cancelled → fail closed (HD-B5-002). U08: **no** session-status rule locked. |
| **Explicit or silent** | **SILENT** for U08 |
| **Conflict / silence** | **SILENCE** — must not assume U06/U07 Cancelled guard automatically applies |
| **U05 evidence** | Cancels session + withdraws actives; Absent may remain; race with U08 possible |
| **Race** | Concurrent U05 + U08 on same active seat; post-U05 U08 on Absent / leftover active |

### HD-U08-004 — Fingerprint

| Field | Content |
|-------|---------|
| **Source** | Design Lock §20; DR-006; U06/U07 handlers |
| **Existing rule** | Every Phase 7.2 writer: required key + fingerprint + same-COMMIT + conflict; event ≠ idemp id |
| **U06 fingerprint** | `schema_version`, `exam_session_id`, `enrollment_id`, `seat_number` |
| **U07 fingerprint** | `schema_version`, `exam_session_id`, `enrollment_id`, `target_status`, `seat_number` |
| **U05 fingerprint** | `schema_version`, `exam_session_id` |
| **Classification** | **RESOLVED BY EXISTING LOCKED PATTERN** — reuse enrollment-writer pattern; no new business decision |

**Authoritative U08 fingerprint reuse (when later implemented under separate AuthZ):**

```text
schema_version
exam_session_id
enrollment_id
(+ exam_enrollment_id when present on command identity — consistent with seat identity)
operation identity implied by command name Exams.CancelExamEnrollment
```

Do **not** invent a parallel fingerprint schema.

---

## 6. Decision Ballot

### HD-U08-001 — Already Withdrawn / Repeat Cancel

**Question:** If `exam_enrollments.status = Withdrawn` and `CancelExamEnrollment` is invoked again, what is the authoritative behavior?

| Option | Behavior |
|--------|----------|
| **A** | **IDEMPOTENT NO-OP** — no second withdrawal mutation; no second domain/outbox event; business state remains Withdrawn; request may still participate in idempotency key replay/conflict rules |
| **B** | **FAIL CLOSED** — reject; no mutation; no success event; no successful idempotency record for the rejected business operation |
| **C** | **SUCCESS ONLY THROUGH SAME IDEMPOTENCY KEY REPLAY** — same key+payload may replay prior success; a **new** idempotency key against an already Withdrawn seat fails |

**Recommendation (RECOMMENDATION ONLY — not approval):**

```text
A — IDEMPOTENT NO-OP
```

Aligns with HD-7.2-002A session already-Cancelled semantics and reduces U05→U08 duplicate-event risk.

**Current approval state:**

```text
HUMAN DECISION REQUIRED
RECOMMENDATION ONLY — NOT APPROVED
```

---

### HD-U08-002 — Absent → Withdrawn

**Question:** May `CancelExamEnrollment` perform `Absent → Withdrawn`?

| Option | Behavior |
|--------|----------|
| **A** | **FORBIDDEN / FAIL CLOSED** — Absent is not active; not in locked allowlist |
| **B** | **ALLOWED under DR-002** — expand matrix; CURRENT grade still fail-closed |
| **C** | **Treat as already inactive / NO-OP** — no mutation to Withdrawn; no (or no new) withdraw event |

**Locked matrix today (do not expand silently):**

| From | To | Locked? |
|------|-----|---------|
| Registered | Withdrawn | YES |
| Confirmed | Withdrawn | YES |
| Present | Withdrawn | YES |
| Absent | Withdrawn | **NOT LISTED** |
| Withdrawn | active | FORBIDDEN |

**Consequences:**

| Option | Consequence |
|--------|-------------|
| A | Absent seats remain Absent after U05; no U08 cleanup to Withdrawn |
| B | Requires Design Lock transition-table amendment; enables post-U05 Absent cleanup |
| C | Soft cleanup semantics without status change; event/idempotency rules must be specified |

**Recommendation (RECOMMENDATION ONLY — not approval):**

```text
A — FORBIDDEN / FAIL CLOSED
```

Preserves “locked transitions only”; does not silently expand the matrix.

**Current approval state:**

```text
HUMAN DECISION REQUIRED
RECOMMENDATION ONLY — NOT APPROVED
```

**Exact amendment required if approved:**

| If approved | Amendment target |
|-------------|------------------|
| A | Design Lock §7 — add explicit row: Absent → Withdrawn = **FORBIDDEN** (U08) |
| B | Design Lock §7 — add Absent → Withdrawn = **ALLOWED** via CancelExamEnrollment + DR-002 |
| C | Design Lock §8 CancelExamEnrollment — add Absent = inactive no-op semantics + event rule |

```text
AMENDMENT NOT YET AUTHORIZED
```

---

### HD-U08-003 — Cancelled Session × U08

**Question:** When `exam_session.status = Cancelled` and `CancelExamEnrollment` is invoked, what is allowed?

| Option | Behavior |
|--------|----------|
| **A** | **ALL U08 FORBIDDEN** when session is Cancelled |
| **B** | **ALLOW U08 only for still-active seats** and only if DR-002 passes |
| **C** | **ALLOW ONLY Absent → Withdrawn cleanup** (implies Absent→Withdrawn allowed — couples to HD-U08-002) |

**Interactions to decide together:**

| Concern | Notes |
|---------|-------|
| Race with U05 | Both may target same active seat; row locks + HD-U08-001 decide post-state |
| Already Withdrawn | Depends on HD-U08-001 |
| Absent | Survives U05; depends on HD-U08-002 + option C coupling |
| CURRENT grade | DR-002 remains fail-closed whenever withdrawal would mutate |
| Event cause | U08 must still use `exam_enrollment_cancel` (never `exam_session_cancel`) |
| Idempotency | Same Phase 7.2 writer rules; fingerprint per HD-U08-004 pattern |
| Concurrency | Session + enrollment locks; no inventing new lock objects in this ballot |

**Recommendation:**

```text
RECOMMENDATION INTENTIONALLY NON-BINDING
(readiness audit did not bind A/B/C)
```

Product must choose; do not assume U06/U07 Cancelled fail-closed applies automatically.

**Current approval state:**

```text
HUMAN DECISION REQUIRED
BLOCKING
RECOMMENDATION ONLY — NOT APPROVED
```

**Exact amendment required if approved:**

| If approved | Amendment target |
|-------------|------------------|
| A | Design Lock §8 CancelExamEnrollment — add: session Cancelled → U08 **FAIL CLOSED** |
| B | Design Lock §8 — add: Cancelled session + still-active + DR-002 → U08 **ALLOWED**; else fail |
| C | Design Lock §7 + §8 — Absent→Withdrawn + Cancelled-session cleanup path (requires HD-U08-002 ≠ A) |

```text
AMENDMENT NOT YET AUTHORIZED
```

**Dependency note:** Option **C** is incompatible with HD-U08-002 option **A**. Human approval must keep HD-U08-002 and HD-U08-003 consistent.

---

### HD-U08-004 — Idempotency Fingerprint

**Question:** Exact fingerprint fields for CancelExamEnrollment?

**Status:**

```text
RESOLVED BY EXISTING LOCKED PATTERN
```

No new business ballot required. When U08 is later authorized to implement, reuse Phase 7.2 enrollment-writer fingerprint conventions (see Evidence Matrix §5).

---

## 7. Impact Matrix

| Area | HD-U08-001 | HD-U08-002 | HD-U08-003 |
|------|------------|------------|------------|
| **U05** | Duplicate withdraw events if not no-op/fail clear | Absent left after cascade | Race / post-cancel cleanup |
| **U06** | None direct | None direct | Contrast: U06 already fail-closed on Cancelled |
| **U07** | None (no withdraw) | Absent created via U07 may stick | Contrast: U07 HD-B5-002 fail-closed on Cancelled |
| **U08** | Defines repeat semantics | Defines Absent eligibility | Defines session Cancelled gate |
| **U09** | None direct | None direct | Present seats on Cancelled rare post-U05 |
| **Outbox** | A avoids duplicate cancel events | B/C may emit more withdraw events | Cause must stay `exam_enrollment_cancel` |
| **Idempotency** | Interacts with key replay vs business no-op | Fingerprint still command-scoped | Same |
| **Concurrency** | U05↔U08 | Low | U05↔U08 high |
| **Grades** | DR-002 unchanged | DR-002 if B | DR-002 unchanged |
| **Attendance** | None | None | None |
| **Session lifecycle** | None | None | Post-Cancelled mutation policy |
| **School isolation** | Unchanged | Unchanged | Unchanged |

---

## 8. Required Amendment

After **human approval** of HD-U08-001 / 002 / 003, the following locked artifacts must be amended (in order):

1. **Design Lock** — `.cursor/database/phase-7.2/04-PHASE-7.2-DESIGN-LOCK.md`  
   - §7 transition matrix (esp. Absent→Withdrawn if explicit)  
   - §8 CancelExamEnrollment (repeat Withdrawn; Cancelled session rule)
2. Optionally record approvals in a **04B-style approval record** or ballot follow-up (repository convention).
3. Gate `05` row notes for U08 only if needed for clarity — **not** an implementation AuthZ.

```text
AMENDMENT NOT YET AUTHORIZED

This task does NOT execute Design Lock amendments.
This task does NOT approve options A/B/C.
Human must explicitly approve each HD before any amendment or readiness re-audit PASS.
```

---

## 9. Readiness Consequence

```text
Until HD-U08-001, HD-U08-002, and HD-U08-003 are formally resolved (APPROVED / LOCKED):

U08 = BLOCKED
Batch 6 = NOT AUTHORIZED
U09 = NOT AUTHORIZED

U08 IMPLEMENTATION AUTHORIZATION = NOT GRANTED
```

After formal approval of all three:

```text
NEXT STEP = U08 READINESS RE-AUDIT
(not automatic implementation authorization)
```

---

## 10. Final Status

```text
HD-U08-001: A — APPROVED (IDEMPOTENT NO-OP) — recorded in 08 + Design Lock DL-7.2-U08-001
HD-U08-002: A — APPROVED (Absent → Withdrawn FORBIDDEN) — recorded in 08 + Design Lock DL-7.2-U08-001
HD-U08-003: B — APPROVED (Cancelled session: active seats only + DR-002) — recorded in 08 + Design Lock DL-7.2-U08-001
HD-U08-004: RESOLVED BY EXISTING LOCKED PATTERN

Batch 6: NOT AUTHORIZED
U08 implementation: NOT AUTHORIZED
Implementation: NOT AUTHORIZED

Design Lock: APPROVED / AMENDED / LOCKED (DL-7.2-U08-001)

NEXT STEP: U08 READINESS RE-AUDIT
```

```text
This resolution artifact originally required human decision.
Approvals are now recorded in:
  .cursor/database/phase-7.2/08-PHASE-7.2-BATCH-6-U08-HUMAN-DECISION-APPROVAL-RECORD.md
and encoded in Design Lock revision DL-7.2-U08-001.

Approval ≠ Batch 6 AuthZ ≠ U08 implementation AuthZ.
Do not implement U08 from this document alone.
```
