# MASTER PHASE 7 — PHASE 7.3
# READINESS DISCOVERY (READ-ONLY)

---

```text
Document Type:
READINESS DISCOVERY

Subphase:
PHASE 7.3 — GRADE HARDENING / POLICY COMPLETION

Date:
2026-09-12

Mode:
READ-ONLY

Implementation:
NONE

Start AuthZ:
00-PHASE-7.3-HUMAN-SUBPHASE-START-AUTHORIZATION.md — APPROVED
```

---

## 1. Subphase Identity

```text
PHASE 7.3 = Grade Hardening / Policy Completion

NOT:
  Phase 7.2 session/enrollment lifecycle (CLOSED)
  Phase 7.4 Results aggregation
  Phase 7.5 GPA / Ranking / Transcript tables
  HTTP grade API exposure (unless separately authorized later)
```

---

## 2. Parent Locked Decisions (consume; do not reopen casually)

| ID | Lock | 7.3 relevance |
|----|------|----------------|
| **P7-D5** | Idempotency REQUIRED for externally exposed mutating Grade + Exam Admin commands | Grade keys today **optional** (`!== null`); Exam Admin 7.1/7.2 largely required non-empty keys |
| **P7-D6** | Letter/GPA/credits/weights NOT authoritative on `student_grades` | Preserve; no store-derived SSOT |
| **P7-D7** | Enrollment date-window **DEFERRED** | May resolve in 7.3 or remain deferred |
| **P7-D9** | Year LIST partitions; **no DEFAULT**; fail-closed if missing | Manager + fail-closed **PROVEN** on Enter/Correct; year auto-create partition may need ops hardening |
| Grade SSOT | `score` / `max_score` / `is_absent`; no hard-delete | PROVEN |
| Submitted workflow | Enum reserved; workflow **NOT IMPLEMENTED** | Candidate 7.3 decision |
| Vocab gaps | Excused / Withheld / Incomplete | DEFERRED historically — ballot |

---

## 3. Live Implementation Snapshot (evidence)

| Surface | Finding |
|---------|---------|
| Enter / Correct / Void / Finalize handlers | PRESENT |
| Idempotency | Optional when key null — **gap vs strict P7-D5 “REQUIRED”** if treated as already externally exposed |
| Partition check | `StudentGradesPartitionManager::partitionExists` on Enter/Correct — fail-closed |
| Timing policy (U11) | CLOSED — Enter InProgress\|Completed |
| Cancelled Correct guard (U12) | CLOSED |
| HTTP grade writers | Not Phase 7.3 default scope (confirm in ballot) |
| `results.*` tables | Empty schema — **out of 7.3** (7.4+) |
| `exam.session.cancel` | ABSENT — do not touch |

---

## 4. Candidate Work Packages (for ballot — not authorized)

| ID | Package | Risk | Notes |
|----|---------|------|-------|
| **WP-73-01** | Enforce non-empty idempotency keys on Grade mutating commands (align P7-D5) | MED | Behavior change for callers omitting keys |
| **WP-73-02** | Same-COMMIT idempotency/outbox hardening review for grades | MED | Evidence + residual docs |
| **WP-73-03** | Partition ops: ensure year-create creates `student_grades` partition (no DEFAULT) | MED | Ops + possibly Application hook — Design first |
| **WP-73-04** | Resolve P7-D7 date-window OR explicitly keep DEFERRED | HIGH product | Needs policy |
| **WP-73-05** | Submitted workflow (Submit command) OR keep DEFERRED | HIGH product | Enum already reserved |
| **WP-73-06** | Excused/Withheld/Incomplete vocabulary | HIGH product | Prefer DEFER unless policy ready |
| **WP-73-07** | Additional grade security/negative AuthZ tests | LOW | Hardening only |
| **WP-73-08** | Open/Close/Present RLS writer gaps (from 7.2 conditions) | LOW–MED | Optional; not core “grade hardening” — may DEFER |

---

## 5. Explicit Non-Goals

```text
Do not physicalize results / GPA / transcripts
Do not add DEFAULT partition
Do not hard-delete grades
Do not invent exam.session.cancel
Do not reopen Phase 7.2 unit closures
Do not start Phase 8
```

---

## 6. Recommended Path

```text
1) Human marks Design Decision Ballot (02)
2) Phase 7.3 Design Lock (only approved WPs)
3) Human Implementation Authorization (per unit/WP)
4) Implement → Audit → Closure
5) Phase 7.3 Final Gate
6) Then Phase 7.7 / Master Final Closure path
```

---

## 7. STOP

```text
READINESS DISCOVERY: COMPLETE
Implementation: NOT STARTED
Await: Human Design Decision Ballot (02)
```
