# MASTER PHASE 7 — PHASE 7.1 — EXAM ADMINISTRATION

# 09-PHASE-7.1-EXAM-ADMINISTRATION-DECISION-RESOLUTION-AUDIT

**Document Type:** SECURITY / QUERY / IDEMPOTENCY / PHASE-BOUNDARY DESIGN DECISION RESOLUTION AUDIT  
**Date:** 2026-09-11  
**Phase:** MASTER PHASE 7 — Assessment / Exams / Grades  
**Subphase:** PHASE 7.1 — Exam Administration CQRS  
**Authorization:** READ-ONLY AUDIT ONLY — **NO IMPLEMENTATION**

```text
NO PHP · NO SQL · NO MIGRATIONS · NO DDL · NO RLS · NO PERMISSIONS
NO ROLE MAPPING · NO ROUTES · NO POLICIES · NO TESTS · NO CONFIG
NO SEED · NO DATA · NO REPAIR · NO SILENT RESOLUTION
NO IMPLEMENTATION AUTHORIZATION
```

**Upstream:** [`08-PHASE-7.1-EXAM-ADMINISTRATION-CQRS-RE-READINESS-AUDIT.md`](./08-PHASE-7.1-EXAM-ADMINISTRATION-CQRS-RE-READINESS-AUDIT.md) — **BLOCKED** (68/100)

---

## 1. Executive Summary

After Design Lock amendment, **DR-001 / DR-002 / DR-003 / DR-004 / DR-005a / DR-006** are **design-resolved**. Phase 7.1 still cannot pass a CQRS re-readiness gate until humans resolve remaining decision gaps—primarily **DR-005 role→permission ownership** and **Exam Administration query scope**.

| Area | Classification |
|------|----------------|
| DR-005 role mapping | **REQUIRES HUMAN DECISION** (P0) |
| `exam.*` catalog presence | **IMPLEMENTATION PREREQUISITE** after DR-005 (P1) |
| Query scope (commands-only vs commands+queries) | **REQUIRES DESIGN DECISION** (P1) |
| Minimum query contract | Conditional on query-scope decision |
| Same-COMMIT idempotency | **IMPLEMENTATION DESIGN PREREQUISITE** — existing table + UnitOfW can support; **no schema redesign required** by evidence |
| DR-006 identity separation | **RESOLVED** (design) |
| Confirmed→Present | **REQUIRES HUMAN DECISION** — blocks **only** that transition (P2 for whole-phase; P1 for Present path) |
| 7.1 / 7.2 command ownership | **REQUIRES HUMAN DECISION** (P1 ambiguity) |
| DR-004 / DR-001 / DR-002 | **RESOLVED** |
| RLS | **RESOLVED WITH CONDITIONS** (sufficient for design) |
| Hard-delete triggers on foundation | **NOT A BLOCKER** / P2 hardening |
| Concurrency race matrix | **RESOLVED** (criteria); tests = implementation obligation |
| Outbox event catalog | **RESOLVED** (names); payload detail = implementation condition |
| Implementation authorization | **NOT GRANTED** (separate gate) |

```text
FINAL VERDICT:
DESIGN DECISION RESOLUTION REQUIRED
```

---

## 2. Audit Authorization

| Item | Status |
|------|--------|
| Mode | READ-ONLY |
| Artifact | `.cursor/database/phase-7.1/09-PHASE-7.1-EXAM-ADMINISTRATION-DECISION-RESOLUTION-AUDIT.md` |
| Allowed | Inspect docs, code, migrations, security, CQRS infra, tests, git |
| Forbidden | Any change except this artifact; any assignment/repair/implementation |

---

## 3. Evidence Base

| Path | Use |
|------|-----|
| `phase-7/02-MASTER-PHASE-7-DESIGN-LOCK.md` (AMENDED) | Authoritative lock |
| `phase-7/06-…DESIGN-LOCK-AMENDMENT.md` | Human decision trace |
| `phase-7/07-…AMENDMENT-GATE.md` | Amendment PASS; not implementation-ready |
| `phase-7.1/08-…RE-READINESS-AUDIT.md` | Prior BLOCKED re-readiness |
| `config/security.php` | Live roles/permissions catalog |
| `database/migrations/2026_09_06_110000_*` | `audit.idempotency_keys` / `audit.outbox_messages` |
| `app/Infrastructure/Persistence/Idempotency/EloquentIdempotencyStore.php` | Store uses `DB::table` |
| `app/Infrastructure/Persistence/EloquentUnitOfWork.php` | `DB::transaction` |
| Grade + Attendance Cancel handlers | Current post-commit idempotency usage; Attendance payload-conflict pattern |
| `database/migrations/2026_09_10_150000/150100_*` | Exam foundation + FORCE RLS |
| `docs/sis/exams/SECURITY-CONTRACT.md` | Grades roles only |

---

## 4. Authority Hierarchy

```text
1. Amended Master Phase 7 Design Lock
2. Phase 7.1 Design Lock Amendment / human decision record
3. Amendment gate
4. Explicit approved DCR decisions
5. Schema / migrations
6. Application architecture
7. Security infrastructure
8. Grade / Attendance CQRS patterns
9. Tests
10. Older planning (incl. 04 — stale where contradicted)
```

Where `04` conflicts with amended `02`: **amended Master Lock wins**.

---

## 5. DR-005 Security Resolution

### 5.1 Existing authoritative roles (PROVEN in `config/security.php`)

| Role | Domain evidence |
|------|-----------------|
| `student_manager` / `student_viewer` | Students |
| `enrollment_manager` / `enrollment_viewer` | Academic enrollment |
| `grades_manager` / `grades_teacher` / `grades_viewer` | Grades SoD |
| `attendance_viewer` / `attendance_teacher` / `attendance_manager` | Attendance |

**PROVEN ABSENT as authoritative roles:** Examiner, Registrar, Exam Officer, Assessment Officer.

Master Lock **forbids inventing** those roles and **forbids auto-mapping** all `exam.*` to `grades_manager` / attendance / enrollment / teachers / viewers.

### 5.2 Evidence-supported candidates (NOT approved)

| Candidate role | Why candidate only | Risk if auto-assigned |
|----------------|--------------------|------------------------|
| `grades_manager` | Closest academic/assessment admin; has grades SoD; **no** exam setup today | Conflates grade write SoD with exam lifecycle / cancel power |
| `grades_teacher` | Can `grades.create` only | Over-broad if given exam admin; under-fit for cancel |
| `grades_viewer` | View-only | Not a write candidate |
| `attendance_manager` | Has session cancel pattern analogy | Different BC; cancel semantics differ |
| `enrollment_manager` | Has enrollment.cancel analogy | Academic enrollment ≠ exam seat |

```text
STATUS: ALL mappings = EVIDENCE-SUPPORTED CANDIDATE only
NO APPROVED MAPPING exists in repository or Master Lock.
```

### 5.3 Decision requirement

**REQUIRES HUMAN DECISION** for each `exam.*` permission → which existing role(s) (or approved new role after Security Change Control).

Until then: AuthZ chain cannot be completed without inventing policy → **P0**.

---

## 6. `exam.*` Permission Analysis

### Design vocabulary (Master Lock §21.2 — LOCKED as vocabulary)

```text
exam.create
exam.update
exam.cancel
exam.session.create
exam.session.update
exam.session.open
exam.session.close
exam.enrollment.create
exam.enrollment.update
exam.enrollment.cancel
```

**Note:** Prompt listed `exam.session.cancel`. Authoritative Master Lock vocabulary **does not** include `exam.session.cancel`. CancelExamSession is mapped to `exam.session.update` (or dedicated) with soft wording → see Human Decision HD-005.

| Permission | In catalog? | Assigned? | Role known? | Policy? | Middleware/HTTP? |
|------------|-------------|-----------|-------------|---------|------------------|
| All `exam.*` above | **NO** | **NO** | **NO** | **NO** | **NO** |
| `grades.*` | YES | YES | YES | YES (Grade) | YES |

| Permission | Candidate role(s) | Evidence | Human decision? | Risk if unresolved |
|------------|-------------------|----------|-----------------|--------------------|
| `exam.create` | grades_manager (candidate) | academic admin adjacency | **YES** | Wrong setup authority |
| `exam.update` | grades_manager (candidate) | lifecycle control | **YES** | Status abuse |
| `exam.cancel` | grades_manager / attendance_manager analogy only | high-impact cancel | **YES** | Destructive cancel without SoD |
| `exam.session.*` | grades_manager / attendance_teacher analogies | session open/close patterns | **YES** | Invigilation vs setup blend |
| `exam.enrollment.*` | grades_manager / enrollment_manager analogies | seating vs academic enrollment | **YES** | Wrong seater + grade SoD clash |

**Catalog creation** = **IMPLEMENTATION PREREQUISITE** after mapping decision + implementation authorization — not a separate product design if vocabulary stays as locked.

### D2 — `exam.cancel` (DR-005a)

| Concern | Status |
|---------|--------|
| Vocabulary locked | **YES** |
| Role ownership locked | **NO** — UNRESOLVED |
| Policy semantics (dedicated, not inferred from update/grades/attendance/enrollment) | **YES** (design) |
| Cross-school protection | SchoolContext + FORCE RLS design; admin writers absent |
| Current-grade guard | **LOCKED** (DR-001) — separate from AuthZ |

```text
Vocabulary approval ≠ permission assignment approval.
```

---

## 7. Query Scope Decision

### Evidence for Option A (commands + queries in 7.1)

* Sub-phase title: “Exam Administration **CQRS**” implies Command **and** Query.
* Architecture stack expects queries for read paths.
* Re-readiness audit `08` treated unlocked queries as P1 for full CQRS readiness.

### Evidence for Option B (commands now; queries later)

* Master Lock §9 freezes a **command** surface; **no** GetExam/ListExam contracts.
* Phase **7.6** is “READ MODELS / ACCESS” but text targets **student/guardian grade reads**, not exam-admin operational reads.
* No explicit sentence: “Exam Administration queries deferred.”

### Ambiguity

```text
Master Lock does NOT explicitly defer Exam Administration queries.
Master Lock also does NOT lock any Exam Administration query contracts.
```

**Classification:** **REQUIRES DESIGN DECISION** (not EXPLICITLY DEFERRED).

Human must choose:

* **OPTION A** — Phase 7.1 includes admin queries (then lock minimum contract §8), or  
* **OPTION B** — Phase 7.1 = commands only; admin queries deferred to named later sub-phase (explicit Design Change / Lock amendment).

---

## 8. Minimum Query Contract Requirements

**Only required if OPTION A is chosen.** Do not invent DTO fields.

| Query | Decisions required before lock |
|-------|--------------------------------|
| `GetExam` | Input: exam id + school; AuthZ permission (e.g. view? — **view permission not even in vocab**); include cancelled?; year bound |
| `ListExams` | Filters: year, term, status; pagination; ordering; school isolation |
| `GetExamSession` | Input: session id; parent exam consistency; school |
| `ListExamSessions` | By exam id; status filters; ordering |
| `GetExamEnrollment` | Seat id; whether to expose grade linkage |
| `ListExamEnrollments` | By session; status; pagination; PII minimization |

**Additional vocabulary gap if A:** design vocab has **no** `exam.view` / `exam.session.view` / `exam.enrollment.view`. Human must either:

* add view permissions, or  
* reuse a write permission for reads (generally poor SoD), or  
* bind reads to an existing `*.view` pattern with explicit approval.

Fields needing explicit approval (examples — not invented finals): exam name/dates/status; session times/room/max_grade; seat_number/status; whether CURRENT grade indicators appear on admin seat reads.

---

## 9. Same-COMMIT Idempotency Analysis

### Locked requirement (Master Lock §14.4)

```text
business mutation + outbox + idempotency persistence → SAME TRANSACTION / SAME COMMIT
Do not redesign audit.idempotency_keys
```

### Current pattern (PROVEN)

Grade handlers + Attendance Cancel:

1. `find` before work  
2. mutation + `outbox->stage` **inside** `UnitOfWork::transaction`  
3. `idempotency->store` **after** commit  

### Infrastructure capability (PROVEN)

| Component | Fact |
|-----------|------|
| `EloquentUnitOfWork` | `DB::transaction(...)` |
| `EloquentIdempotencyStore::store` | `DB::table(...)->updateOrInsert` — **participates in ambient DB transaction** if called inside the callback |
| Table PK | `(key, command_name)` |
| Columns | `response_payload` JSON, `expires_at` — **no school_id column** |
| Outbox | `id` identity; `correlation_id` separate; staged via Eloquent create |

### Answers to required questions

| # | Question | Answer |
|---|----------|--------|
| 1 | Can existing infra support same-tx idempotency? | **YES** — call `store` inside the same `UnitOfW` callback as mutation + outbox |
| 2 | Schema redesign required? | **NO** by current evidence |
| 3 | New usage pattern only? | **YES** (handler transactional placement + conflict checks) |
| 4 | Table supports required semantics? | **YES** for key+command uniqueness + JSON result; school/payload conflict via **application comparison** of stored payload (Attendance Cancel pattern) |
| 5 | Replay before mutation? | **YES** — `find` first (existing) |
| 6 | Same key / different payload fail closed? | **Not built into store**; must be **handler policy** (Attendance proves pattern) |
| 7 | School mismatch fail closed? | Store in payload + compare on replay; also enforce SchoolContext before mutate |
| 8 | Atomic commit of all three? | **YES** if store inside `DB::transaction` with mutation/outbox |
| 9 | Rollback removes all three? | **YES** under single Laravel transaction |
| 10 | Replay avoids second event? | **YES** if find short-circuits before staging; must not stage on replay path |

**Classification:** **IMPLEMENTATION DESIGN PREREQUISITE** (not a new product Design Decision; not an Infrastructure Gap requiring DDL).

Copying Grade/Attendance **post-commit** `store` into Phase 7.1 would **violate** the locked contract → implementation must not copy that placement.

---

## 10. DR-006 Identity Separation

| Identity | Source | Interchangeable with idempotency key? |
|----------|--------|----------------------------------------|
| Command idempotency | `X-Idempotency-Key` + `command_name` | N/A (is the idempotency identity) |
| Idempotency row | PK `(key, command_name)` | Related to command replay only |
| Aggregate ID | exam / session / enrollment id | **NO** |
| Outbox message ID | `audit.outbox_messages.id` | **NO** |
| Event type / payload | domain event | **NO** |
| correlation_id | CorrelationContext / outbox column | May correlate; **≠** idempotency key |
| causation_id | Design envelope field | Locked in shape; implementation detail |

**Design:** **RESOLVED** — EVENT IDENTITY ≠ IDEMPOTENCY IDENTITY.

**Ambiguity for implementers:** `causation_id` is in Master Lock envelope list but **not** a column on current `outbox_messages` (only `correlation_id`). Classify as **P2 implementation/envelope mapping** — may live inside JSON payload; does not reopen DR-006.

---

## 11. Confirmed → Present Decision

Master Lock §10.3:

```text
Confirmed → Present
Actor: Exam admin / invigilator mapping TBD
Permission: exam.enrollment.update
Preconditions: Session open policy TBD
Notes: Role mapping DEFERRED
```

| Question | Locked answer? |
|----------|----------------|
| Who may Present? | **NO** — TBD |
| Permission | Design points to `exam.enrollment.update` only |
| Must session be Open/InProgress? | **NO** — “Session open policy TBD” |
| Present while Scheduled? | **UNKNOWN** |
| Background/CLI Present? | **UNKNOWN** / not authorized by design |
| Same actor as OpenExamSession? | **UNKNOWN** |

**Classification:** **REQUIRES HUMAN DECISION**

**Blocks:** **only** `UpdateExamEnrollment` Present transition — **not** all Phase 7.1 CQRS (CreateExam/CancelExam/etc. can be designed without Present). Treat as **scoped blocker** for that transition; whole-phase re-readiness may proceed if Present is explicitly deferred inside Update allowlist.

---

## 12. Phase 7.1 / 7.2 Boundary

### Locked command surface (P7-D4 INCLUDE — not phase-partitioned)

All of: Create/Update/Cancel Exam; Create/Update/Open/Close/Cancel ExamSession; Create/Update/Cancel ExamEnrollment.

### Sub-phase naming (§30)

```text
7.1 — EXAM ADMINISTRATION CQRS
7.2 — EXAM SESSION / ENROLLMENT LIFECYCLE
```

### Mapping (evidence-based; not invented ownership)

| Command | Phase ownership |
|---------|-----------------|
| CreateExam / UpdateExam / CancelExam | **Likely 7.1** (name “Exam Administration”) but **not explicitly exclusive** |
| Session + Enrollment commands | **Ambiguous** — could be 7.1 (CQRS surface) or 7.2 (lifecycle title) |
| CompleteExam / PublishExam / RegisterExam / BulkExamImport | **Forbidden / Absent** — not in Lock |

**Classification:** **REQUIRES HUMAN DECISION** to partition command ownership (or explicitly declare “7.1 implements full P7-D4 surface; 7.2 = hardening/residual”).

Until resolved: risk of scope creep or split implementation without gate clarity → **P1 ambiguity**.

---

## 13. DR-004 Reconfirmation

| Scenario | Locked rule |
|----------|-------------|
| Zero sessions | Completed **FAIL CLOSED** |
| ≥1 session; all non-cancelled Completed; none Scheduled/InProgress | Completed allowed from InProgress |
| Cancelled sessions | Remain Cancelled; do not auto-close |
| Auto session close | **Forbidden** |

**Classification:** **RESOLVED**

Implementation/test obligations only (predicates + zero-session tests). No redesign.

---

## 14. DR-001 / DR-002 Reconfirmation

| Rule | Status |
|------|--------|
| CURRENT = `student_grades.is_current = true` | **LOCKED** |
| CancelExam / CancelExamEnrollment must not mutate grades | **LOCKED** |
| Historical VOIDED do not independently block | **LOCKED** |
| Correct does not clear CancelExam CURRENT guard | **LOCKED** |
| Database cancel-guard trigger | **Not required by Lock**; app + tx predicates |
| Concurrency races | Mandatory future tests (criteria locked) |

**Classification:** **RESOLVED** (design). Implementation + race-test obligations remain.

---

## 15. Security Chain

| Layer | Design | Implementation | Human decision? | Reusable infra? |
|-------|--------|----------------|-----------------|-----------------|
| HTTP / routes | Required when exposed | **Missing** | No (after auth) | Laravel/Sanctum patterns |
| Authentication | Required | Platform exists | No | YES |
| SchoolContext | Locked | Middleware exists | No | YES |
| Permission catalog | Vocab locked | **Missing exam.*** | **DR-005** | Catalog mechanism YES |
| Role mapping | **Unresolved** | N/A | **YES P0** | — |
| Policy | Required | **Missing** | After DR-005 | GradePolicy pattern YES |
| Command / Handler | Locked names | **Missing** | No | sis:make-command YES |
| Domain rules | Partially locked | Aggregates missing | Present TBD | Enums exist |
| Repository | Needed | **Missing** | No | Grade repo pattern YES |
| UnitOfW / RLS / Outbox | Locked | Platform exists | Idempotency placement | YES |
| Idempotency | Locked same-COMMIT | Pattern must change vs Grade | No new product decision | Store YES |
| Audit | Required | Platform exists | Naming soft | YES |

---

## 16. RLS / School Isolation

| Table | FORCE RLS | school_id | Design sufficiency |
|-------|-----------|-----------|--------------------|
| `exams.exams` | YES | YES | **Sufficient** |
| `exams.exam_sessions` | YES | YES | **Sufficient** (year via parent) |
| `exams.exam_enrollments` | YES | YES | **Sufficient** (+ app year match) |
| `exams.exam_types` | No RLS | global | Locked as global |

**Classification:** **RESOLVED WITH CONDITIONS** — app must still enforce SchoolContext + composite ownership; background/CLI exam admin not designed (forbid until authorized).

---

## 17. Historical Integrity / Delete Policy

| Concern | Classification |
|---------|----------------|
| Hard delete forbidden (policy) | **LOCKED** |
| Grade reject-DELETE trigger | **PROVEN** |
| No reject-DELETE on exams/sessions/enrollments | **P2** — acceptable as **application invariant** for design readiness; optional future DB hardening |
| Not a P0/P1 design blocker | **YES** |

---

## 18. Concurrency

| Race | Policy locked? | Ambiguity? |
|------|----------------|------------|
| Six mandatory races from amendment | **YES** (acceptance criteria) | No design reopen |
| Zero-row transition FAIL CLOSED | **YES** | — |
| Row locking / `UPDATE … WHERE status=` | Implementation technique | **P2** — choose predicates consistent with Attendance Cancel style |

**Classification:** **RESOLVED** (policy); tests + predicate technique = implementation.

---

## 19. Outbox / Event Contract

| Concern | Status |
|---------|--------|
| Event catalog names | **LOCKED** (DR-006) |
| Classes exist | **NO** (expected) |
| Aggregate / school / actor / occurred_at / correlation | Envelope **LOCKED** |
| Full payload field lists | **Not fully locked** — **P2** implementation detail under envelope |
| Replay vs new event | Same-COMMIT + find-first — implementation obligation |
| causation_id column | Absent on table; map via payload or later hardening — **P2** |

---

## 20. Implementation Authorization Status

```text
DESIGN RESOLUTION ≠ IMPLEMENTATION AUTHORIZATION
```

Even after all human decisions + a passing re-readiness gate:

```text
CQRS implementation MUST NOT begin
until explicit human implementation authorization is provided.
```

Current status: **NOT GRANTED** (Master Lock / amendment / gate / this audit).

---

## 21. Decision Matrix

| Decision | Status | Severity | Evidence | Human Decision Required? | Implementation Dependency |
|----------|--------|----------|----------|--------------------------|---------------------------|
| DR-005 role mapping | REQUIRES HUMAN DECISION | **P0** | Master Lock §21.3; security.php | **YES** | Blocks AuthZ/policies/routes |
| exam.* catalog | IMPLEMENTATION PREREQUISITE | **P1** | ABSENT in config | After DR-005 | Create under impl auth |
| Query scope | REQUIRES DESIGN DECISION | **P1** | No lock; not explicit defer | **YES** (A vs B) | Gates query work |
| Query contract | Conditional | **P1** if A | No Get*/List* | If A: YES | Handlers/DTOs |
| Same-COMMIT idempotency | IMPLEMENTATION DESIGN PREREQUISITE | **P1** (compliance) | UnitOfW + DB::table store | **NO** new product decision | Must not copy post-commit |
| Event/idempotency identity | RESOLVED | — | §14.5 / §22.4 | No | Preserve in code |
| Confirmed→Present | REQUIRES HUMAN DECISION | **P2** whole / **P1** Present path | §10.3 TBD | **YES** or defer Present | UpdateExamEnrollment |
| 7.1/7.2 boundary | REQUIRES HUMAN DECISION | **P1** | §30 vs P7-D4 | **YES** | Scope of first impl slice |
| DR-004 | RESOLVED | — | §10.1 | No | Tests/predicates |
| DR-001 / DR-002 | RESOLVED | — | §9.3 | No | Guards + races |
| RLS | RESOLVED WITH CONDITIONS | — | 150100 | No | Use SchoolContext |
| Hard delete | NOT A BLOCKER | **P2** | No foundation triggers | Optional hardening | App forbid delete |
| Concurrency | RESOLVED (criteria) | **P2** tests | §23.2 | No | Race tests |
| Outbox | RESOLVED (catalog) | **P2** payload | §22.2 | No | Event classes later |
| Implementation authorization | NOT GRANTED | **P1** process | Lock header | **YES** (separate) | All code/DDL |

---

## 22. P0 / P1 / P2 / P3 Register

### P0

| ID | Finding |
|----|---------|
| **DR-09-001** | DR-005 role→permission mapping unresolved |

### P1

| ID | Finding |
|----|---------|
| **DR-09-010** | `exam.*` permissions absent (after mapping) |
| **DR-09-011** | Query scope Option A vs B unresolved |
| **DR-09-012** | If A: minimum query + view-permission vocabulary unresolved |
| **DR-09-013** | Same-COMMIT idempotency usage must be designed into handlers (no DDL) |
| **DR-09-014** | 7.1 vs 7.2 command ownership unresolved |
| **DR-09-015** | Implementation authorization not granted |

### P2

| ID | Finding |
|----|---------|
| **DR-09-020** | Confirmed→Present actor/session-open policy TBD (scoped) |
| **DR-09-021** | Foundation reject-DELETE triggers absent |
| **DR-09-022** | Race tests not written |
| **DR-09-023** | Outbox payload/`causation_id` mapping detail |
| **DR-09-024** | Soft `exam.session.cancel` vs `exam.session.update` wording |

### P3

| ID | Finding |
|----|---------|
| **DR-09-030** | Document `04` stale Cancel TBD sections |
| **DR-09-031** | FEATURE-CONTRACT still grade-only |

---

## 23. Exact Decisions Required From Human

## HUMAN DECISIONS REQUIRED

### HD-001 — DR-005 Role → Permission Mapping

* **Question:** For each locked `exam.*` permission, which **existing** authoritative role(s) receive it (or is a new role approved under Security Change Control)?  
* **Why it matters:** Without this, policies/routes cannot be authorized without inventing mappings.  
* **Options supported by evidence:** Map subsets to `grades_manager` / split open-close like attendance teacher/manager / introduce approved new role / hybrid.  
* **Forbidden assumptions:** Auto-map all `exam.*` → `grades_manager`; invent Examiner/Registrar/Exam Officer without approval.  
* **Recommended option:** None declared as approved — human must choose; **candidates only** listed in §5.  
* **Locks after decision:** Role↔permission matrix for Phase 7.1 AuthZ.

### HD-002 — Exam Administration Query Scope

* **Question:** Option **A** (7.1 includes admin queries) or Option **B** (commands only; queries deferred to named later phase)?  
* **Why it matters:** Determines whether unlocked queries remain a re-readiness P1.  
* **Options:** A or B as in §7.  
* **Forbidden:** Inventing Get*/List* contracts without lock; assuming 7.6 covers admin ops reads.  
* **Recommended:** Evidence slightly favors making the choice explicit; **no auto-pick**. If speed-to-writers is priority, B is coherent **only if** Lock is amended to say so.  
* **Locks after decision:** Query inclusion/deferral statement in Master Lock.

### HD-003 — If HD-002 = A: Minimum Query + View AuthZ

* **Question:** Which queries are in 7.1 minimum set, and what view permission vocabulary applies?  
* **Why it matters:** Vocab currently has no `exam.view`.  
* **Options:** Add view permissions; or defer lists and ship Get-only; etc.  
* **Forbidden:** Inventing DTO field lists without approval.  
* **Locks after decision:** Query contract + view permissions.

### HD-004 — Phase 7.1 vs 7.2 Command Ownership

* **Question:** Does 7.1 implement the full P7-D4 command surface, or only Exam-level commands with Session/Enrollment in 7.2?  
* **Why it matters:** Prevents split-brain implementation and gate confusion.  
* **Options:** (1) 7.1 = full surface; 7.2 = residual hardening; (2) 7.1 = Exam commands only; 7.2 = Session+Enrollment; (3) other explicit partition.  
* **Forbidden:** Expanding into Results/GPA/CompleteExam/Bulk.  
* **Locks after decision:** Sub-phase command ownership table.

### HD-005 — Optional: `exam.session.cancel` dedicated permission

* **Question:** Keep CancelExamSession under `exam.session.update`, or add dedicated `exam.session.cancel`?  
* **Why it matters:** Soft wording in Lock; prompt listed dedicated name.  
* **Forbidden:** Silent catalog invention without decision.  
* **Locks after decision:** Permission for CancelExamSession.

### HD-006 — Confirmed → Present (or explicit deferral)

* **Question:** Who may Present, and must session be InProgress/Open? Or defer Present transition out of first 7.1 slice?  
* **Why it matters:** Blocks Present path only.  
* **Forbidden:** Inventing invigilator role.  
* **Locks after decision:** UpdateExamEnrollment Present matrix or explicit deferral.

### HD-007 — Implementation Authorization (separate)

* **Question:** After design decisions + re-readiness PASS, grant Phase 7.1 implementation authorization?  
* **Why it matters:** Process gate — not substitutable by design docs.  
* **Forbidden:** Treating this audit or re-readiness PASS as code authorization.

---

## 24. Recommended Next Gate

```text
1. Human decisions HD-001 … HD-006 (minimum: HD-001, HD-002, HD-004)
2. Master Design Lock amendment (or security decision record) capturing approved mappings / query scope / phase boundary
3. Phase 7.1 CQRS RE-READINESS AUDIT (re-run / successor to 08)
4. Explicit HUMAN IMPLEMENTATION AUTHORIZATION
5. Implementation (only then)
```

Do **not** start PHP/SQL/permission edits at this gate.

---

## 25. Final Verdict

```text
DESIGN DECISION RESOLUTION REQUIRED
```

Not used:

```text
READY FOR IMPLEMENTATION
DESIGN RESOLUTION COMPLETE — READY FOR RE-READINESS AUDIT
```

(Reason: P0 DR-005 and P1 query-scope / 7.1–7.2 ownership still require human decisions.)

```text
NO IMPLEMENTATION WAS PERFORMED.

No PHP, SQL, migration, DDL, RLS, permission,
role mapping, route, policy, configuration, seed,
or test changes were made.
```
