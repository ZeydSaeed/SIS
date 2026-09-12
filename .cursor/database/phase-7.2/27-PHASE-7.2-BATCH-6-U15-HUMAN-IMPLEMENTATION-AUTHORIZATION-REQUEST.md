# MASTER PHASE 7
# PHASE 7.2
# BATCH 6
# U15
# HUMAN IMPLEMENTATION AUTHORIZATION REQUEST

---

```text
Document Type:
HUMAN IMPLEMENTATION AUTHORIZATION REQUEST → GRANTED

Unit:
U15 — RLS writer-path verification

Gate:
7.2-U15

Phase:
7.2

Batch:
6

Mode:
IMPLEMENTATION AUTHORIZED BY HUMAN (2026-09-12)

Implementation:
AUTHORIZED (see audit artifact 28)

U14:
CLOSED / ACCEPTED WITH CONDITIONS
(see 26-PHASE-7.2-BATCH-6-U14-HUMAN-CLOSURE-REVIEW-RECORD.md)

U16:
NOT AUTHORIZED — DO NOT IMPLEMENT
```

| Field | Value |
|-------|-------|
| **Date** | 2026-09-12 |
| **Gate row** | `05-PHASE-7.2-IMPLEMENTATION-AUTHORIZATION-GATE.md` → **7.2-U15** |
| **Design Lock** | `04-PHASE-7.2-DESIGN-LOCK.md` §21 **DD-019** |
| **Governing decision** | **DD-019** — ARCHITECTURE RESOLUTION |
| **04B record** | DD-019 retained as Architecture Resolution — writer-path PG tests; **no RLS change** |
| **Resolution source** | `03-PHASE-7.2-HUMAN-DECISION-RESOLUTION.md` — DD-019 / RLS matrix |
| **Prior evidence** | Phase 7.1 `17-PHASE-7.1-EXAM-ADMINISTRATION-RLS-RUNTIME-VERIFICATION.md` (consume; ≠ U15 AuthZ) |
| **This document** | Request only — ≠ authorization grant |

```text
Creating this request ≠ APPROVE U15

U14 closure ≠ U15 authorization

Batch authorization ≠ U15 unit authorization

Design readiness ≠ implementation authorization
```

---

## 1. Authorization State

```text
MASTER PHASE 7:
AUTHORIZED

PHASE 7.2:
AUTHORIZED / DESIGN LOCK

BATCH 6:
OPEN

U13: CLOSED / ACCEPTED WITH CONDITIONS
U14: CLOSED / ACCEPTED WITH CONDITIONS
U15: IMPLEMENTATION AUTHORIZATION: GRANTED (2026-09-12)
U15: Human decision: APPROVE U15 IMPLEMENTATION — DD-019
U16: NOT AUTHORIZED
```

---

## 2. Current Phase / Batch State

```text
Phase 7.2 Design Lock: IN FORCE
Batch 6: OPEN
Sequential units U13–U14: CLOSED WITH CONDITIONS
U15: next candidate for human AuthZ review only
```

---

## 3. U13 Dependency State

```text
U13: CLOSED / ACCEPTED WITH CONDITIONS
Artifact: 23-PHASE-7.2-BATCH-6-U13-HUMAN-CLOSURE-REVIEW-RECORD.md
U15 functional dependency on U13 room isolation: NONE
U15 must not reopen or redesign U13
```

---

## 4. U14 Dependency State

```text
U14: CLOSED / ACCEPTED WITH CONDITIONS
Artifacts:
  25-PHASE-7.2-BATCH-6-U14-IMPLEMENTATION-AUDIT.md
  26-PHASE-7.2-BATCH-6-U14-HUMAN-CLOSURE-REVIEW-RECORD.md
U15 functional dependency on U14 event causation: NONE
U15 must not reopen or redesign U14
```

---

## 5. U14 Retained Conditions

```text
1. Parallel race verification: DEFERRED / NON-BLOCKING
2. C-001 PHPUnit exit-code: RETAINED / NON-BLOCKING
```

```text
U14 conditions remain NON-BLOCKING for U15 authorization review.
Authoritative Batch 6 closures do not require resolving either condition before U15 AuthZ review.
Do NOT silently remove these conditions.
Do NOT reopen U14 solely because they remain.
```

---

## 6. U15 Gate

| Field | Locked value (Gate **7.2-U15**) |
|-------|----------------------------------|
| Unit name | RLS writer-path verification |
| Surface | PG tests |
| Database / schema | None |
| Permission | N/A |
| RLS posture | **Verify only** |
| Events | N/A |
| Expected proof | same-school / cross-school; missing GUC; FORCE |
| Governing | **DD-019** |
| Forbidden | RLS policy mutation |
| Gate AuthZ status | **NOT AUTHORIZED** (until separate human grant) |

---

## 7. U15 Governing Design

```text
Design Lock §21 — DD-019 ARCHITECTURE RESOLUTION (preserved):

No RLS mutation authorized by this Design Lock
FORCE RLS / school isolation on sessions/enrollments remains as already proven

Required future verification (before Phase 7.2 closure, after implementation AuthZ):
writer-path PostgreSQL tests
(same-school / cross-school / missing GUC / FORCE RLS)

Not authorized:
  enable/disable RLS
  change FORCE RLS
  change policies
  change WITH CHECK
```

```text
Option A pattern (Architecture Resolution):
Document USING-only as acceptable under PostgreSQL semantics;
require dedicated PG tests for INSERT/UPDATE writer paths —
same-school, cross-school, missing GUC, FORCE RLS.
Do NOT amend policies to add explicit WITH CHECK under U15.
```

---

## 8. Governing HD / DD / DR

| Authority | Role for U15 |
|-----------|----------------|
| **DD-019** | Governing Architecture Resolution |
| Design Lock §21 | Locked verification requirement + no RLS mutation |
| 04B §8 | Confirms DD-019 Architecture Resolution |
| Gate **7.2-U15** | Unit definition / proof matrix |
| Phase 7.1 artifact **17** | Prior FORCE RLS proof (consume as evidence; ≠ U15 AuthZ) |
| HD ballot | **N/A** — not a human option ballot for verification requirement |

```text
Note: DDR 7.2-DD-019 historical “PARTIAL / PROPOSAL” Final Decision row is superseded by
Design Lock §21 + 03 Architecture Resolution + 04B.
Do not reopen that historical DDR status.
```

---

## 9. Design Resolution Status

```text
LOCKED DESIGN: YES (Architecture Resolution)
DESIGN AMBIGUITY: NONE for U15 verification scope
UNRESOLVED HUMAN DECISION: NONE required for verification-only U15
ARCHITECTURE RESOLUTION: DD-019 — IN FORCE

U15:
DESIGN READY / NOT AUTHORIZED
```

```text
Competing U15 decisions: NONE
Human decision required to authorize RLS policy amendment: YES — and that amendment remains OUT OF SCOPE / NOT AUTHORIZED under U15
```

---

## 10. Locked Scope (authoritative table)

| Field | Required value |
|-------|----------------|
| Unit | U15 — RLS writer-path verification |
| Gate | **7.2-U15** |
| Governing decision | **DD-019** (Architecture Resolution) |
| Surface | PostgreSQL writer-path tests for Phase 7.2 session/enrollment (and related exams.*) RLS behavior |
| Expected proof | same-school ALLOW path; cross-school DENY; missing GUC fail-closed; FORCE RLS still forced |
| Database | **None** (no migration / schema / index / constraint / data migration) |
| RLS | **Verify only** — no policy mutation |
| Permissions | **N/A** — no permission add/rename/broaden |
| HTTP | **None** — no routes/controllers/API |
| Tests | Focused PG/Feature Database tests proving writer-path isolation matrix |
| Dependencies | U13/U14 closed (Batch sequential handoff); Phase 7.1 FORCE RLS baseline; Phase 7.2 writers already exist under prior AuthZ |
| Forbidden scope | RLS policy mutation; WITH CHECK amendment; U16; redesign prior units |

---

## 11. Explicit In-Scope Items (IF later authorized)

```text
1. Add/extend PostgreSQL runtime verification tests for Phase 7.2 writer paths on
   exam_sessions / exam_enrollments (and exams graph tables already under FORCE RLS)
   covering at minimum:
   — same-school writer path succeeds under correct app.current_school_id
   — cross-school writer path fails closed
   — missing GUC fails closed
   — FORCE RLS remains enabled/forced (catalog / behavioral proof)
2. Mirror Phase 7.1 artifact 17 verification discipline for 7.2 writers
   without copying unauthorized RLS changes.
3. Document results in a U15 implementation audit artifact.
4. Preserve existing SchoolContext + FORCE RLS architecture.
5. STOP — do not open U16.
```

---

## 12. Explicit Out-of-Scope Items

```text
U16 Permission/role registration
enable / disable RLS
change FORCE RLS
change RLS policies
add/change WITH CHECK clauses
migrations / schema / indexes / constraints / data migrations
new tables / new columns
permission catalog changes
HTTP routes / controllers / API resources
redesign of U09–U14 semantics
grade mutation / attendance mutation
outbox / idempotency redesign
event causation redesign (U14)
room isolation redesign (U13)
```

---

## 13. Security Boundary

```text
Permissions:
  N/A for U15 as verification unit (Gate: N/A)
  Do NOT add / rename / broaden permissions under U15

RLS:
  Verify only — FORCE RLS / school isolation already proven baseline
  Do NOT mutate policies

School isolation:
  Must prove same-school vs cross-school writer behavior under GUC

Fail-closed:
  missing GUC → fail closed
  cross-school → fail closed

Authorization checks:
  U15 does not replace application authority ports
  U15 verifies database RLS enforcement on writer paths

Cross-school protection:
  Explicit expected proof (Gate)

Audit / outbox / idempotency:
  U15 MUST NOT redesign outbox or idempotency
  U15 MUST NOT merge event identity with idempotency identity
  Writer tests may observe existing writers; must not invent new security model
```

```text
Unapproved security model change required by locked U15 design: NO
```

---

## 14. Database Boundary

```text
Migrations: NOT AUTHORIZED
Schema changes: NOT AUTHORIZED
Indexes: NOT AUTHORIZED
Constraints: NOT AUTHORIZED
Data migrations: NOT AUTHORIZED
New tables: NOT AUTHORIZED
New columns: NOT AUTHORIZED
```

```text
Locked U15 design = verification tests only.
No conflict requiring DCR: Design Lock forbids RLS mutation; Gate forbids RLS policy mutation.
```

---

## 15. RLS Boundary

```text
RLS policy mutation: FORBIDDEN
enable/disable RLS: FORBIDDEN
change FORCE RLS: FORBIDDEN
change WITH CHECK: FORBIDDEN
Verification of existing FORCE RLS / school isolation: REQUIRED (if later authorized)
```

---

## 16. HTTP Boundary

```text
Routes: NOT AUTHORIZED
Controllers: NOT AUTHORIZED
Form requests / API resources: NOT AUTHORIZED
HTTP contracts: NOT AUTHORIZED
HTTP permissions: NOT AUTHORIZED
```

---

## 17. Permission Boundary

```text
Permission registration: NOT AUTHORIZED under U15
(that is U16 scope — separately gated; DO NOT IMPLEMENT)
Permission rename/broaden: FORBIDDEN
```

---

## 18. Testing Requirements

### Focused U15 tests (if later authorized)

```text
PostgreSQL writer-path tests proving:
  — same-school INSERT/UPDATE (or established writer path) under correct school GUC
  — cross-school DENY / fail-closed
  — missing GUC DENY / fail-closed
  — FORCE RLS remains in effect for sessions/enrollments (and related exams.* as scoped by artifact 17 mirror)
```

### Security tests

```text
Cross-school protection
Missing GUC fail-closed
No RLS bypass assumption
```

### Architecture tests

```text
php artisan architecture:validate --fitness
(after AuthZ implementation of tests only)
```

### Regression tests

```text
U09 Present
U10 CancelExam cascade
U11 Enter timing
U12 Correct Cancelled
U13 Room isolation
U14 Event causation
Do not redesign those units
```

### Database / RLS tests

```text
Explicitly REQUIRED by Gate 7.2-U15 / DD-019 (PG tests) — if later authorized
Prefer existing project PG test locations/helpers (e.g. tests/Feature/Database/PostgreSql/)
Do not create a parallel testing framework
```

```text
This AuthZ-request task creates NO tests and runs NO implementation validation.
```

---

## 19. Regression Requirements

```text
If later authorized, U15 must not regress:
  U09 / U10 / U11 / U12 / U13 / U14 semantics
  Phase 7.1 FORCE RLS baseline
  DR-006 event ≠ idempotency identity
  SchoolContext authority fail-closed behavior
```

---

## 20. Dependencies

```text
Prerequisites satisfied for AuthZ review:
  U13 CLOSED / ACCEPTED WITH CONDITIONS
  U14 CLOSED / ACCEPTED WITH CONDITIONS

Dependency readiness: YES for human AuthZ review
Dependency readiness ≠ implementation authorization

Upstream evidence (consume only):
  Phase 7.1 artifact 17 RLS runtime verification
  Existing FORCE RLS migrations on exams.*
  Phase 7.2 session/enrollment writers already authorized in prior units
```

---

## 21. STOP Conditions

STOP before/during any future U15 work (do not resolve by assumption) if:

```text
U15 design is unresolved
U15 governing decision conflicts with another locked decision
A new human decision is required (e.g. product demands WITH CHECK amendment)
A Design Change Request is required
Database changes exceed the locked scope
RLS changes exceed the locked scope (any policy mutation)
Permission changes exceed the locked scope
HTTP changes exceed the locked scope
A previous unit must be reopened
U14 conditions unexpectedly become blocking (authoritative evidence only)
A new event identity / idempotency identity model is required
The requested behavior contradicts existing Phase 7 semantics
PostgreSQL is unavailable and tests cannot be proven without inventing a non-PG substitute design
```

This AuthZ-request task found:

```text
U15 design: LOCKED (DD-019 Architecture Resolution) — DESIGN READY
Conflicts: NONE
Unapproved DB/RLS/HTTP/permission required by lock: NO
U14 conditions blocking U15 AuthZ review: NO
```

---

## 22. Implementation Rules IF Authorized (future only)

After a **separate** explicit human APPROVE for U15 ONLY:

```text
1. Implement ONLY locked U15 scope: PG writer-path verification tests.
2. Verify existing FORCE RLS / school isolation — do not mutate policies.
3. Prove same-school / cross-school / missing GUC / FORCE matrix.
4. Reuse existing PG test infrastructure and Phase 7.1 artifact 17 discipline.
5. Run architecture:validate --fitness, security:validate, Pint on touched files.
6. Produce U15 implementation audit artifact.
7. STOP — do not implement U16.
```

```text
ONLY the locked U15 scope is allowed after AuthZ.
Explicitly prohibit:
  U16
  unrelated refactoring
  database expansion
  security expansion (policy mutation)
  HTTP expansion
  permission expansion
  redesign of prior units (U09–U14)
```

---

## 23. Forbidden Scope

```text
FORBIDDEN under this request and under any future narrow AuthZ unless separately amended:

* implementing U15 without a separate human APPROVE grant
* implementing U16
* RLS policy mutation / FORCE RLS change / WITH CHECK amendment
* migrations / schema / indexes / constraints / data migrations
* permission catalog changes
* HTTP / routes / controllers / API changes
* redesign of U09–U14
* unrelated refactoring
* grade mutation / attendance mutation
* outbox / idempotency redesign
* treating this document as authorization
```

---

## 24. Human Authorization Gate

```text
U15 IMPLEMENTATION AUTHORIZATION:

GRANTED (2026-09-12)

Human APPROVE for U15 ONLY — DD-019 writer-path PG verification.
U16 remains NOT AUTHORIZED.
```

---

## 25. Post-Grant State

```text
U13: CLOSED / ACCEPTED WITH CONDITIONS
U14: CLOSED / ACCEPTED WITH CONDITIONS
U15: AUTHORIZED → see 28-PHASE-7.2-BATCH-6-U15-IMPLEMENTATION-AUDIT.md
U16: NOT AUTHORIZED / NOT IMPLEMENTED
BATCH 6: OPEN
```
