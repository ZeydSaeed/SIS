# SIS DATABASE — PHASE 3C.7  
# GRADUATION / COMPLETION ARCHITECTURE

**Document type:** AUDIT + ARCHITECTURE + DESIGN LOCK ONLY  
**Date:** 2026-09-10  
**Predecessor:** Phase 3C.6 Gate (`PASS WITH CONDITIONS`)  
**Status:** ARCHITECTURE DESIGNED — academic graduation policies UNRESOLVED  

```text
NO DDL · NO MIGRATIONS · NO APPLICATION CODE · NO GRADUATION ENGINE
IMPLEMENTATION AUTHORIZATION: NOT GRANTED
HUMAN APPROVAL REQUIRED
```

---

## 1. Mission

```text
GRADUATION / COMPLETION
```

is a **governed derived academic outcome**. It consumes governed evidence from Grades SSOT and Results (Term/Annual/GPA as policy requires). It is **not** SSOT for grades, enrollments, results, GPA, ranking, or transcript.

### Preferred dependency (non-forced)

```text
Grades SSOT
  ├─ Term Results
  └─ Annual Results
        ├─ GPA (only if policy requires)
        └─ Graduation / Completion Eligibility
              └─ Graduation / Completion Decision → Award
```

**NOT automatic:**

```text
Annual → GPA → Graduation     (only if policy requires GPA)
Transcript → Graduation       (FORBIDDEN as authority)
Ranking → Graduation          (FORBIDDEN unless explicit future policy)
Promotion ≡ Graduation        (FORBIDDEN)
```

---

## 2. Completion vs Graduation (LOCKED distinction)

| Concept | Meaning |
|---------|---------|
| **Completion** | Requirements satisfied under approved completion policy |
| **Graduation** | Authorized institutional decision/award recognizing completion |

They need **not** occur at the same moment. Architecture supports: completed not approved; eligible awaiting approval; approved; awarded/graduated; superseded/corrected; partial/transfer; multi-program.

---

## 3. Concept Separation (do not collapse)

Must remain distinct:

1. Academic Completion  
2. Graduation Eligibility  
3. Graduation Decision  
4. Graduation Approval  
5. Graduation Award  
6. Graduation Date  
7. Completion Date  
8. Program Completion  
9. Enrollment Completion  
10. Academic Year Closure  
11. Promotion  
12. Withdrawal  
13. Transfer  
14. Dismissal/Termination (if applicable)  
15. Transcript Issuance  
16. Transcript Publication  

**Anti-pattern:** sole `is_graduated` boolean as academic truth.  
`StudentStatus::Graduated` (LIVE enum) = **student projection / lifecycle status**, not Graduation SSOT (see Audit).

---

## 4. Audit Findings

| Finding | Classification |
|---------|----------------|
| LIVE `graduation.*` tables | **Absent** (empty reserved schema) |
| Blueprint `graduation.eligibility_rules` (`min_gpa` NOT NULL, `min_credit_hours`, `required_subjects` JSONB) | **STALE sketch** — invents thresholds; **not policy authority** |
| Blueprint `graduation.records` (student/enrollment/year, `final_gpa`, `honors`, `graduation_number`) | **STALE sketch** — incomplete vs versioning/school-on-record/provenance |
| Blueprint `promotion.rules.min_gpa` | **STALE sketch** — **Promotion ≠ Graduation** |
| `StudentStatus::Graduated` | **CURRENT BUT NON-AUTHORITATIVE** for graduation truth — projection only |
| `certificates.issued_certificates.graduation_id` blueprint FK | **STALE sketch** — consumer link candidate |
| PHASE-D lifecycle guide | **CURRENT BUT NON-AUTHORITATIVE** planning — no approved thresholds |
| Optimization/Intelligence ranking | **Out of scope** — not academic graduation |
| HD-01…HD-18 | **Preserved** unresolved/deferred as prior |
| Hard-coded graduation thresholds in app code | **Not found** as approved policy |

```text
AUTHORITATIVE: Phase 3C.0–3C.6 locks + this phase design
NON-AUTHORITATIVE: blueprint graduation/promotion threshold sketches
```

---

## 5. Program / Curriculum Extensibility

LIVE/current academic model is largely **subject-based** under enrollment/year/term. Architecture preserves that anchor while allowing requirement evidence against:

subject · course · module · competency · training unit · practical · internship · capstone/project  

Do **not** redesign curriculum in this phase.

---

## 6. Requirement / Evidence Model

```text
Completion Requirement (versioned policy)
  → Requirement Evaluation
       → Evidence Set / Evidence Items (typed source_type + source_id)
            → Result: SATISFIED | NOT_SATISFIED | EXEMPT | N/A | PENDING | BLOCKED | …
```

Categories are **extension points**, none mandatory until policy (HD-20…).

Missing evidence ≠ PASS/SATISFIED (fail closed where official).

---

## 7. Lifecycle / Versioning / Provenance (summary)

See Lifecycle, Data Model, Rebuild artifacts.

Operational evaluation ≠ Official completion ≠ Approval ≠ Award ≠ Publication.

Official outcomes: versioned, policy/calc pinned, source set, fingerprints **plus** full provenance trail (fingerprint ≠ entire audit).

---

## 8. Human Authority

Default architecture permits:

```text
Machine evaluation → Human review → Human approval → Official award
```

Do **not** assume automatic graduation. Exact authority matrix = **HD-31** (and related).

---

## 9. Correction Propagation

```text
Grade correction → Outbox → Term/Annual/GPA impact
  → Completion re-evaluation (candidate version)
  → Graduation decision impact
  → Transcript impact (3C.6 / HD-11)
  → Human action per HD-35/36 — NOT automatic revoke
```

---

## 10. Boundaries

| Boundary | Rule |
|----------|------|
| Transcript | Displays completion/graduation; never creates graduation truth |
| Ranking | Not prerequisite; Optimization ranks irrelevant |
| GPA | Optional evidence only if policy requires; no hidden dependency |
| Promotion | Separate lifecycle; `min_gpa` sketch ≠ graduation policy |
| Academic year closure | Independent of graduated |
| Certificates | Downstream consumer of award (future) |

---

## 11. Identity

Graduation/completion attaches to **school + enrollment/program context**, not `student_id` alone.

**PROPOSED business identity axes:**

```text
school_id + enrollment_id (+ program/specialization context) + completion_scope
```

Typed evidence refs: `source_type + source_id` (or equivalent).

---

## 12. School Isolation & Security

```text
SchoolContext → Command/Query → Graduation Identity → Composite ownership → RLS → FORCE RLS
```

Fail closed. No client-provided school_id / URL / artifact id as sole authz.

---

## 13. CQRS / Outbox (conceptual)

**Commands:** EvaluateCompletion, RecalculateCompletion, RequestGraduationApproval, ApproveGraduation, SupersedeGraduation, Correct/Revoke (if policy), PublishGraduation (if applicable)  

**Queries:** GetCompletionStatus, GetRequirementEvaluation, GetGraduationHistory, GetGraduationProvenance, GetPendingApprovals  

**Events (conceptual, align naming with existing Results/grade events):** COMPLETION_EVALUATED, GRADUATION_APPROVED, GRADUATION_SUPERSEDED, … plus upstream GRADE_*/RESULT_*/GPA_* impacts.

Do not duplicate taxonomies; map to existing outbox conventions at implementation time.

---

## 14. Concurrency Controls

Separate: Idempotency · Concurrency · Version conflict · Fingerprint equivalence.  
Fingerprint is **not** an idempotency key.

---

## 15. Performance Blueprint

Patterns: school+year pending approvals; enrollment completion status; requirement evaluations; history/provenance; batch year-end evaluation; correction fan-out; reporting MVs (future).

```text
NO INDEXES · NO PARTITIONS THIS PHASE
```

Future indexes from EXPLAIN ANALYZE + measured workload. Partitioning evidence-based later (DL-010 spirit).

---

## 16. Bounded Context (HD-42 / DL-012)

**Decision (architectural recommendation, not microservice split):**

Graduation/Completion is an **Academic Lifecycle** concern that **consumes** Results evidence. Physically remains in the **same modular monolith** (DL-012). Logical module may be `graduation` / lifecycle adjacent to Results — **not** a separately deployable service.

HD-16 remains transcript packaging (3C design / 3D issue).  
HD-42 covers graduation module packaging vs Results implementation phasing — **UNRESOLVED** for exact delivery phase naming; architecture does not force microservices.

---

## 17. New Design Locks (PROPOSED for human acceptance)

| ID | Lock |
|----|------|
| **DL-017** | Completion ≠ Graduation; both versioned derived outcomes |
| **DL-018** | Graduation/Completion never Grade/Results/GPA/Ranking/Transcript SSOT |
| **DL-019** | Official graduation/completion outcomes immutable in place; supersede only |
| **DL-020** | Requirement evaluation requires explicit evidence; missing ≠ satisfied |
| **DL-021** | GPA/Ranking/Promotion/Year-closure are non-hidden optional/independent unless policy pins them |
| **DL-022** | StudentStatus::Graduated is projection only, not graduation SSOT |

These are **proposed locks** pending human acceptance with Gate.

---

## 18. Anti-Patterns Rejected

`is_graduated` as sole truth · hard-coded GPA/credits/subject counts · auto-grad on enrollment/annual/transcript · transcript/ranking as graduation SSOT · promotion≡graduation · mutable official row · hard delete · no provenance/policy/calc pins · cross-school · student_id-only multi-program identity · fingerprint-only idempotency · app-only tenancy · missing=pass · irreversible autonomous graduation · silent revoke after grade correction  

---

## 19. Policy Neutrality

All thresholds and academic rules = **HUMAN DECISION REQUIRED (HD-19…HD-42)**. Blueprint `min_gpa` / credits are **not** approved.
