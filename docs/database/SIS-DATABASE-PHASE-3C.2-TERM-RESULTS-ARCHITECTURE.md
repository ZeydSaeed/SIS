# SIS DATABASE — PHASE 3C.2  
# TERM RESULTS ARCHITECTURE

**Document type:** ARCHITECTURE DESIGN ONLY  
**Date:** 2026-09-10  
**Predecessor:** Phase 3C.1 Gate (`PASS WITH CONDITIONS`)  
**Status:** ARCHITECTURE DESIGNED — academic rule slots remain UNRESOLVED  

```text
NO DDL · NO MIGRATIONS · NO APPLICATION CODE
IMPLEMENTATION AUTHORIZATION: NOT GRANTED
```

---

## 1. Mission Answer

```text
TERM RESULT
```

is a **versioned, derived, rebuildable, auditable academic projection** for one student academic enrollment within one school, one academic year, and one term — typically at **subject** grain — computed from authoritative `exams.student_grades` plus immutable policy/calculation versions.

It is **not** Grade SSOT (DL-001).

```text
exams.student_grades  →  Term Results  →  Annual / GPA / Ranking / Transcript projections
```

Annual Results must remain rebuildable **without** exclusive dependence on stored Term Results (DL-003).

---

## 2. What a Term Result Represents (core questions)

| # | Question | Architectural answer | Status |
|---|----------|----------------------|--------|
| 1 | Identity | Business identity = school + academic enrollment + academic year + term + subject (+ optional program later); version identity separate | **PROPOSED** |
| 2 | Student | Via `enrollment.student_id` (denorm allowed for read) | **LOCKED** (structure exists) |
| 3 | Academic year | Via enrollment / exams / grades year | **LOCKED** |
| 4 | Term | Via `exams.exams.term_id` graph for contributing grades | **LOCKED** |
| 5 | Enrollment/structure | Anchored on `enrollment.enrollments` + class/section placement | **LOCKED** structure |
| 6 | Contributing grades | Explicit source set of grade composite identities | **LOCKED** requirement |
| 7 | Policy version | Bound on official versions | **LOCKED** (DL-014) |
| 8 | Calculation version | Bound separately | **LOCKED** (DL-008) |
| 9 | Eligibility boundary | Separate HD-04 + HD-18 references | **LOCKED** structure; rules **HDR** |
| 10 | Source fingerprint | Required on official versions | **LOCKED** concept |
| 11 | Result version | Monotonic version per business identity | **PROPOSED** |
| 12 | Lifecycle | Operational vs official states | See Lifecycle doc |
| 13 | Supersession | New version; prior retained | **LOCKED** (DL-007) |
| 14 | Rebuild | Grades + structure + policies + calc + eligibility | **LOCKED** (DL-009/016) |
| 15 | Audit | Actor, reason, correlation, timestamps | **PROPOSED** |
| 16 | Cross-school | SchoolContext + composite school_id + future RLS/FORCE | **LOCKED** (DL-011) |

---

## 3. Aggregation Anchor (existing structure)

**ARCHITECTURAL RECOMMENDATION:** Aggregate Term Results at:

```text
enrollment × term × subject
```

**Evidence:** LIVE grades denorm `subject_id`, `enrollment_id`, `school_id`, `academic_year_id`; exams bind to `term_id`; blueprint `term_results` uniqueness sketch `(enrollment_id, term_id, subject_id)`.

**Not assumed without evidence:** competency/module/credit-unit as primary grain. Credits remain optional schema fields (HD-15).

Assessment contributions:

```text
student → enrollment → term → subject
              ↓
     exam sessions (via exams.term_id + subject)
              ↓
     student_grades (SSOT)
              ↓
     subject-term derived outcome
```

---

## 4. Business Identity vs Version Identity vs Storage Key

| Layer | Meaning | Example concept |
|-------|---------|-----------------|
| **Business identity** | “This student’s Math result for Term 1 of AY X in School S under enrollment E” | `(school_id, enrollment_id, academic_year_id, term_id, subject_id)` |
| **Version identity** | One calculated instance of that business identity | business identity + `result_version` |
| **Storage key** | Future physical PK | May be surrogate + year; **not DDL now** |

**PROPOSED uniqueness for “current” operational/official pointer:** at most one **current** version per business identity **per class** (operational current vs official current — see §5). Exact physical unique strategy = FUTURE IMPLEMENTATION REQUIREMENT.

Do **not** assume all optional dimensions (program, curriculum) are mandatory in the unique key.

---

## 5. Operational vs Official

| Aspect | Operational Term Result | Official Term Result |
|--------|-------------------------|----------------------|
| Purpose | Fast UI / stale-refresh / eventual | Academic historical truth for downstream |
| Consistency | Eventual (DL-013) | Strong at finalize; then immutable except supersede |
| Mutability | May be replaced by newer calculation | Never silent mutate (DL-007) |
| Masquerade | **Forbidden** to present as official (TR-INV-012) | Labeled official/finalized |
| May differ temporarily? | **YES** — architecture permits lag vs official | Official lags until explicit finalize/supersede |

Stale operational projections **must not** be labeled official.

---

## 6. Source Grade Set

Official Term Result versions must identify contributors:

| Element | Required |
|---------|----------|
| Grade composite identity `(id, academic_year_id)` | YES |
| `is_current` at calculation time (recorded) | YES |
| `status` at calculation time | YES |
| `score` / `max_score` / `is_absent` | YES |
| `exam_enrollment_id` / session / subject links | YES |
| School / enrollment / term path | YES |

**Outbox event IDs are not authoritative** (DL-009). Reconstructible from grades + structure + policy selection (HD-06).

---

## 7. Policy & Calculation Binding

```text
Term Result Version
   → grading / weighting / eligibility / rounding policy version ids
   → calculation_version
   → effective scope snapshot (school/year/term as applicable)
```

Later policy publish creates **new** calculations/versions; does **not** rewrite historical official rows (DL-014).

Overlapping EFFECTIVE policy precedence:

```text
HUMAN DECISION REQUIRED
```

---

## 8. Eligibility (kept separate)

| Gate | HD | Stores/references |
|------|----|-------------------|
| Grade-status eligibility | HD-04 | Which statuses included — rule **UNRESOLVED** |
| Dataset completeness | HD-18 | Whether official finalize allowed — rule **UNRESOLVED** |

Architecture must answer *why included/excluded* via recorded eligibility outcomes + contributing set — without inventing the rule.

---

## 9. Weighting / Absence / Retake Compatibility

| Concern | Architecture stance |
|---------|---------------------|
| Weights sum = 100 | **NOT assumed** (HD-03) |
| Absent ⇒ score NULL | **Preserve** LIVE invariant |
| Absent ≠ zero ≠ missing ≠ exempt ≠ incomplete | **Separated** (HD-05) |
| Retake selection | Policy-selected canonical contributors; strategy **HDR** (HD-06) |

---

## 10. NULL / ZERO / N/A Semantics

| Token | Meaning | Status |
|-------|---------|--------|
| `NULL` score with `is_absent=true` | Absent grade LIVE | **LOCKED** |
| `0` score | Numeric zero; **not** absent | **LOCKED** distinction |
| Missing grade | No contributing row | Distinct; treatment **HDR** |
| NOT APPLICABLE | Outcome field unused under policy | Representable; rules **HDR** |
| UNKNOWN / INCOMPLETE | Eligibility or aggregation incomplete | **HDR** (HD-07/18) |

Database defaults must not invent academic meaning — FUTURE IMPLEMENTATION REQUIREMENT: avoid silent DEFAULT 0 on outcome fields.

---

## 11. Historical Truth Path

```text
Grade Correct (VOID+INSERT)
  → new authoritative current grade
  → async invalidate operational Term Result
  → Calculate new Term Result version
  → optional Finalize → new official version
  → prior official retained + SUPERSEDED
```

No `UPDATE old official Result` as the history model (DL-007/015).

---

## 12. CQRS Contracts (conceptual only)

**Commands (PROPOSED names):** `CalculateTermResult`, `FinalizeTermResult`, `SupersedeTermResult`, `RebuildTermResult`  
**Queries:** `GetCurrentOperationalTermResult`, `GetOfficialTermResult`, `GetTermResultHistory`

No God `ResultsService`. No handlers in this phase.

---

## 13. Tenant Isolation

`student_id` alone is **insufficient**. Require school-scoped identity:

```text
school_id (+ enrollment/school composite relationships)
```

Future stack: Policy → SchoolContext → Handler → Composite FK → RLS → FORCE RLS (DL-011). Fail-closed on missing school context / cross-school refs.

---

## 14. Downstream Consumption

GPA / Ranking / Transcript **consume** Term Results as **derived inputs** where useful, but:

- Annual rebuild must still work from **grades** (DL-003)  
- Ranking remains non-SSOT snapshots (DL-005)  
- Transcript issuance remains Phase 3D (HD-16)  

Term Results must not become a writable second grade ledger.

---

## 15. Performance Blueprint (no indexes implemented)

Likely access patterns → **candidate** composites (blueprint only):

| Pattern | Candidate |
|---------|-----------|
| Student term board | `(school_id, enrollment_id, academic_year_id, term_id)` |
| Current version | partial/filtered on current markers |
| Official history | `(business_id, result_version)` |
| Fingerprint lookup | `(source_fingerprint)` |
| Policy/calc audit | `(policy_version_id)`, `(calculation_version)` |
| School term batch | `(school_id, academic_year_id, term_id)` |

**Partitioning:** **NO** initially (DL-010). Future evidence: row growth, vacuum, latency, year-scoped access.

---

## 16. Concurrency Boundaries (design)

Protect conceptually against dual calculate/finalize, rebuild vs correction, duplicate events:

| Mechanism | Role |
|-----------|------|
| Idempotency keys / fingerprint equality | Same inputs ⇒ no uncontrolled new official version |
| Serialization per business identity | FUTURE IMPLEMENTATION |
| Optimistic concurrency on finalize | FUTURE IMPLEMENTATION |
| Distinguish recalc vs new academic truth | Architecture |

---

## 17. Outbox Relationship

```text
Grade change → Outbox → recalculation trigger
```

If outbox fails/duplicates/missing: **rebuild from grades** remains valid (DL-009).

---

## 18. Observability (PROPOSED event concepts)

calculation started/completed/failed · finalized · superseded · rebuild started/completed · rebuild mismatch · fingerprint mismatch · policy/calc-version mismatch  

Names are **PROPOSED ONLY** unless later standardized.

---

## 19. Failure Model Classes

| Class | Examples |
|-------|----------|
| TECHNICAL FAILURE | Infra, missing calc version binary, lock timeout |
| ACADEMIC INELIGIBILITY | HD-18 fail, missing required grades under approved policy |
| HUMAN DECISION REQUIRED | Policy slot empty (HD-03/04/…) |
| SECURITY FAILURE | Missing SchoolContext, cross-school — **fail closed** |

---

## 20. Traceability Matrix (summary)

| Design choice | 3C.0 | 3C.1 | 3C.2 | Future |
|---------------|------|------|------|--------|
| Grade SSOT | DL-001 | Calc contract | This arch | Implement consumers |
| Versioned hybrid term | DL-002 | Catalog | Identity/lifecycle/model | Schema when approved |
| Rebuild from grades | DL-009/016 | Contract + GV-15 | Rebuild doc | Rebuild jobs |
| Policy/calc pins | DL-008/014 | Catalogs | Bindings | Catalog DDL 3C.1 impl |
| Tenant stack | DL-011 | Security note | Isolation section | RLS |
| Weight/status/eligibility rules | HDs | Slots | Accommodated, not filled | After HD resolution |
| GPA/ranking/transcript | DL-004…006 | Catalogs | Out of term SSOT | Later phases |

---

## 21. Final Architectural Q&A

| # | Question | Answer |
|---|----------|--------|
| 1 | Grades still only SSOT? | **YES** |
| 2 | Traceable source grade set? | **YES** (required) |
| 3 | Policy version identifiable? | **YES** |
| 4 | Calculation version identifiable? | **YES** |
| 5 | History survives corrections? | **YES** (supersession) |
| 6 | Rebuild without outbox history? | **YES** |
| 7 | Operational ≠ official? | **YES** |
| 8 | Cross-school preventable? | **YES** (design) |
| 9 | Duplicate calc controlled? | **YES** (idempotency/fingerprint design) |
| 10 | Downstream without new SSOT? | **YES** |
| 11 | 10–20y growth survivable? | **YES** with indexes/evidence partition later |
| 12 | Calc-version coexist with history? | **YES** |
| 13 | Unresolved HDs without schema redesign? | **YES** — policy slots |
| 14 | Hard-delete prohibited? | **YES** for official |
| 15 | Accidental policy from open HDs? | **NO** — marked HDR |

All YES → architecture gate eligible for **PASS WITH CONDITIONS** (HDs still open for implementation).
