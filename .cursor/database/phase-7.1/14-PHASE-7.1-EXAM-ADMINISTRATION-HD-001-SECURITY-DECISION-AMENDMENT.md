# MASTER PHASE 7 — PHASE 7.1 — EXAM ADMINISTRATION — HD-001 SECURITY DECISION AMENDMENT

---

## 1. Document Control

| Field | Value |
|-------|-------|
| **Title** | PHASE 7.1 — Exam Administration — HD-001 Security Decision Amendment |
| **Phase** | MASTER PHASE 7 — Assessment / Exams / Grades |
| **Subphase** | PHASE 7.1 — Exam Administration |
| **Document Type** | HUMAN SECURITY DECISION AMENDMENT |
| **Decision Status** | **HD-001 RESOLVED — APPROVED** |
| **Date** | 2026-09-11 |
| **Mode** | HUMAN SECURITY DECISION AMENDMENT ONLY |
| **Implementation** | **NONE** |

```text
THIS DOCUMENT = human security ownership decision amendment only
≠ HUMAN IMPLEMENTATION AUTHORIZATION (HD-007)
≠ permission catalog registration
≠ role assignment in code
≠ policy / route / handler / HTTP exposure
≠ query authorization
≠ Phase 7.2 authorization
```

---

## 2. Human Decision

The human has explicitly approved the following security ownership mapping for Phase 7.1 exam-administration command permissions:

| Permission    | Approved role    | Decision |
| ------------- | ---------------- | -------- |
| `exam.create` | `grades_manager` | **APPROVED** |
| `exam.update` | `grades_manager` | **APPROVED** |
| `exam.cancel` | `grades_manager` | **APPROVED** |

> The human has approved `grades_manager` as the authorized role owner for all three Phase 7.1 exam-administration command permissions.

This amendment supersedes the prior fail-closed status recorded in `13-PHASE-7.1-EXAM-ADMINISTRATION-HD-001-HUMAN-DECISION-RESOLUTION.md` (`HD-001 = UNRESOLVED`). The mapping is no longer a candidate; it is an **APPROVED** human security ownership decision.

---

## 3. Approved Permission Mapping

| Permission    | Approved role    | Decision | Scope |
| ------------- | ---------------- | -------- | ----- |
| `exam.create` | `grades_manager` | APPROVED | Phase 7.1 CreateExam only |
| `exam.update` | `grades_manager` | APPROVED | Phase 7.1 UpdateExam only |
| `exam.cancel` | `grades_manager` | APPROVED | Phase 7.1 CancelExam only |

### Security Decision Interpretation

| Dimension | Status |
|-----------|--------|
| **Human Security Approval** | **APPROVED** |
| **Candidate Mapping** | No longer merely a candidate |
| **Implementation** | **NOT AUTHORIZED** |
| **HTTP Exposure** | **BLOCKED** |
| **Query Authorization** | **NOT APPROVED / DEFERRED** |
| **Phase 7.2 Authorization** | **NOT PART OF THIS DECISION** |

---

## 4. Security Boundary

This decision means:

* `grades_manager` owns `exam.create`
* `grades_manager` owns `exam.update`
* `grades_manager` owns `exam.cancel`

This decision does **NOT** mean:

* wildcard `exam.*` exists
* all exam permissions automatically belong to `grades_manager`
* query permissions belong to `grades_manager`
* Phase 7.2 permissions belong to `grades_manager`
* session/enrollment lifecycle permissions are granted
* HTTP/API access is automatically authorized
* any implementation has been approved

> Permission ownership is granted only for the three named Phase 7.1 command permissions.

### Dedicated Cancel Permission (Preserved)

| Rule | Status |
|------|--------|
| `exam.cancel` remains a dedicated permission | **PRESERVED** |
| Do not replace with `exam.*` | **FORBIDDEN** |
| Do not collapse into `exam.update` / `exam.manage` / `exam.write` | **FORBIDDEN** |
| Do not introduce `exam.delete` / `exam.remove` / `exam.cancel_all` | **FORBIDDEN** |
| Cancellation remains a dedicated high-impact command (DR-005 / DR-005a) | **INTACT** |

---

## 5. Phase 7.1 Scope

Phase 7.1 remains limited to:

| # | Command | Permission |
|---|---------|------------|
| 1 | `CreateExam` | `exam.create` |
| 2 | `UpdateExam` | `exam.update` |
| 3 | `CancelExam` | `exam.cancel` |

Confirmed:

* No query commands are introduced.
* No session commands are introduced.
* No enrollment commands are introduced.

---

## 6. Phase 7.2 Boundary

The Phase 7.2 boundary is explicitly preserved. The following remain owned by Phase 7.2 and are **NOT** approved by this amendment:

* `CreateExamSession`
* `UpdateExamSession`
* `OpenExamSession`
* `CloseExamSession`
* `CancelExamSession`
* `CreateExamEnrollment`
* `UpdateExamEnrollment`
* `CancelExamEnrollment`

Also preserved:

| Item | Status |
|------|--------|
| `exam.session.update` is the approved vocabulary for session update | **PRESERVED** (HD-005) |
| `exam.session.cancel` remains forbidden | **PRESERVED** (HD-005) |
| Confirmed → Present remains deferred to Phase 7.2 | **PRESERVED** (HD-006) |

No Phase 7.2 permission is assigned to `grades_manager` as part of this amendment.

---

## 7. Query Boundary

> HD-001 resolves command permission ownership only.

* No query authorization is approved.
* No query permission is created or assigned.
* HD-002 (Query scope = COMMANDS ONLY) remains intact.
* HD-003 (Query AuthZ deferred) remains intact.

---

## 8. HD-007 Status

```text
HD-007 — HUMAN IMPLEMENTATION AUTHORIZATION: NOT GRANTED
```

The human security decision does **NOT** constitute implementation authorization.

Do not reinterpret this approval as authorization to:

* add permissions to code
* create policies
* create middleware
* implement commands
* create handlers
* expose HTTP
* create tests for implementation
* modify database schema

Implementation remains blocked until a separate explicit HD-007 authorization is granted.

| Gate | Status |
|------|--------|
| HD-001 (security ownership) | **RESOLVED — APPROVED** |
| HD-007 (implementation authorization) | **NOT GRANTED** |
| HTTP Exposure | **BLOCKED** |

---

## 9. Preserved Design Decisions

This amendment does not weaken or rewrite previously locked decisions.

| Decision | Meaning | Treatment |
|----------|---------|-----------|
| **HD-002** | Query scope = COMMANDS ONLY | **PRESERVED** |
| **HD-003** | Query AuthZ deferred | **PRESERVED** |
| **HD-004** | Phase 7.1 / 7.2 boundary | **PRESERVED** |
| **HD-005** | `exam.session.update` vocabulary; no `exam.session.cancel` | **PRESERVED** |
| **HD-006** | Confirmed → Present deferred to Phase 7.2 | **PRESERVED** |
| **HD-007** | Implementation authorization NOT GRANTED | **PRESERVED** |
| **DR-001** | Current-grade guard and cancellation invariants | **PRESERVED** |
| **DR-004** | Exam Completed invariants | **PRESERVED** |
| **DR-005 / DR-005a** | Dedicated `exam.cancel` vocabulary | **PRESERVED** |
| **DR-006** | Event identity ≠ idempotency identity | **PRESERVED** |

Semantics of the above decisions are unchanged by this amendment.

---

## 10. Preserved Cancel Safety Invariants (DR-001)

Approving `grades_manager` does **NOT** weaken existing CancelExam safety rules.

Future implementation must still fail closed when:

* ANY current grade exists
* ANY session is Completed
* Exam is already Completed
* Exam is already Cancelled

Cancellation remains atomic:

* Exam → Cancelled
* Scheduled/InProgress sessions → Cancelled
* active enrollments → Withdrawn

Never:

* mutate grades
* void grades
* correct grades
* delete grades
* modify historical VOIDED grades

These remain implementation/design invariants and are not changed by HD-001.

---

## 11. Implementation Prohibition

This artifact authorizes **no** implementation.

**Forbidden as a consequence of this document alone:**

```text
config/security.php · Permission.php · roles · policies · middleware · routes
controllers · commands · handlers · services · repositories
migrations · database tables · indexes · RLS
tests · seeders · factories
events · outbox · idempotency
HTTP exposure · UI · API exposure
registration of exam.* · creation of exam.* wildcard
any Phase 7.2 permission assignment
```

```text
No permission or role changes were implemented by this amendment.
```

---

## 12. Final Verdict

> **HD-001 RESOLVED — APPROVED**
>
> `grades_manager` is explicitly approved as the security role owner for:
>
> * `exam.create`
> * `exam.update`
> * `exam.cancel`
>
> This resolution authorizes no implementation. HD-007 remains NOT GRANTED.

```text
MASTER PHASE 7 — PHASE 7.1
HD-001 SECURITY DECISION AMENDMENT

exam.create → grades_manager: APPROVED
exam.update → grades_manager: APPROVED
exam.cancel → grades_manager: APPROVED

HD-001: RESOLVED — APPROVED

Implementation: NONE
HD-007: NOT GRANTED
HTTP Exposure: BLOCKED
Phase 7.2: UNCHANGED
Query Authorization: NOT APPROVED / DEFERRED

Permission ownership granted only for the three named Phase 7.1 command permissions.
exam.cancel remains dedicated. exam.* was not introduced.

Next Required Action:
SEPARATE EXPLICIT HD-007 HUMAN IMPLEMENTATION AUTHORIZATION
(do not proceed to implementation from this amendment alone)
```
