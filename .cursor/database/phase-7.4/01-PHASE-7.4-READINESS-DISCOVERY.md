# MASTER PHASE 7 — PHASE 7.4
# READINESS DISCOVERY

---

```text
Document Type:
READINESS DISCOVERY (READ-ONLY)

Subphase:
PHASE 7.4 — RESULTS / ACADEMIC AGGREGATION

Date:
2026-09-12

Start AuthZ:
00 APPROVED

Implementation:
NONE in this document
```

---

## 1. Live evidence

| Check | Observed |
|-------|----------|
| PostgreSQL | 18.2 |
| `results` schema | **EXISTS** |
| `results.*` relations | **0** |
| `exams.student_grades` | LIVE + FORCE RLS + LIST partitions (no DEFAULT) |
| Grade writers | Enter/Correct/Void/Finalize + idempotency (7.3-U01) |
| Academic years | LIVE table; current count may be 0 |
| Terms | LIVE (`academic.terms`) |
| Master Phase 7 | CLOSED WITH CONDITIONS |
| P7-D2 | **DEFERRED — OWNERSHIP DECISION REQUIRED** (until ballot) |

---

## 2. Design inheritance (design-only → promote candidates)

| Source | Status | Use in 7.4 |
|--------|--------|------------|
| 3C.0 DL-001…DL-016 | PROPOSED / governing direction | Promote to LOCKED via ballot |
| 3C.0 HD-01…HD-18 | Almost all UNRESOLVED | Ballot must lock P0 for term/annual or defer calc |
| 3C.2 Term Results architecture | DOCUMENTED ONLY | Primary logical model for 7.4-U* |
| 3C.3 Annual Results | DOCUMENTED ONLY | Second wave units in 7.4 |
| 3C.4–3C.6 GPA/Ranking/Transcript | DOCUMENTED ONLY | **Out of 7.4** → Phase 7.5 |
| Blueprint `results.term_results` sketch | STALE / incomplete | Non-authoritative vs 3C.2 |
| P7-D8 ranking blueprint `rank_*` | STALE | Ignore; 3C.5 wins when 7.5 opens |

---

## 3. Work package candidates (post Design Lock)

| ID | Package | Depends |
|----|---------|---------|
| **WP-74-01** | Resolve P7-D2 ownership | Ballot HD-7.4-001 |
| **WP-74-02** | Lock term/annual scope vs 7.5 | Ballot |
| **WP-74-03** | Promote DL-001…DL-016 | Ballot |
| **WP-74-04** | Lock P0 calc policies (HD-03/04/05/06/18) | Ballot |
| **WP-74-05** | `results` physical model + FORCE RLS | Design Lock + unit AuthZ |
| **WP-74-06** | Calculate / Finalize / Rebuild Application commands | Schema + policies |
| **WP-74-07** | Annual rollup | Term model stable |
| **WP-74-08** | Outbox bridges from grade events (async supersede hint) | Optional later unit |

---

## 4. Hard prohibitions (carry forward)

```text
No second grade ledger
No writable score store in results.*
No silent mutate of official finalized versions
No GPA/Ranking/Transcript DDL in 7.4 unless ballot overrides (RECOMMENDED: exclude)
No Phase 18 MV substitution for Results BC
No exam.session.cancel
No DEFAULT student_grades partition
No Phase 8
```

---

## 5. Blocking vs non-blocking

| Item | Class |
|------|-------|
| P7-D2 unresolved | **BLOCKING** until ballot |
| HD-01 GPA formula | NON-BLOCKING for 7.4 if GPA excluded |
| HD-02 letter bands | NON-BLOCKING if letters excluded from term v1 |
| HD-08…HD-12 ranking/transcript | NON-BLOCKING (7.5) |
| HD-03/04/05/06/18 | **BLOCKING for official calculator** — ballot must resolve or defer official finalize |
| Empty live years/grades | NON-BLOCKING (ops) |

---

## 6. Recommended first implementation unit (after Lock + AuthZ)

```text
7.4-U01 — results.term_result_versions physical skeleton + FORCE RLS
           (no calculator yet OR calculator behind locked P0 policies)

Preferred sequence:
  U01 schema+RLS
  U02 CalculateTermResult (operational)
  U03 FinalizeTermResult (official)
  U04 RebuildTermResult
  U05 Annual skeleton + calculate/finalize (later)
```

---

## 7. STOP

```text
PHASE 7.4 READINESS: COMPLETE
Continue → Design Decision Ballot
```
