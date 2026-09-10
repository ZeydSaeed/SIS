# SIS DATABASE — PHASE 3C.2  
# TERM RESULT LIFECYCLE

**Document type:** DESIGN ONLY  
**Date:** 2026-09-10  

```text
NO DDL · NO CODE
```

---

## 1. State Model (ARCHITECTURAL RECOMMENDATION)

```text
NOT_CALCULATED
      ↓
CALCULATED          ← operational derived version exists
      ↓
FINALIZED           ← official derived version
      ↓
SUPERSEDED          ← prior official retained after newer official
```

Optional operational markers (not additional academic truth):

| Marker | Meaning |
|--------|---------|
| `STALE` | Inputs changed; recalculation pending/needed |
| `FAILED` | Technical calculation failure |
| `INELIGIBLE` | Academic eligibility gate failed (HD-18) — **not** a substitute for inventing incomplete rules |

`INCOMPLETE` as an official academic outcome remains:

```text
HUMAN DECISION REQUIRED (HD-07 / HD-18)
```

Do not add new GradeStatus values here.

---

## 2. State Definitions

| State | Meaning | Official? | Mutable content? | May be superseded? | Downstream official use |
|-------|---------|-----------|------------------|--------------------|-------------------------|
| NOT_CALCULATED | No derived version yet | NO | N/A | N/A | NO |
| CALCULATED | Operational projection present | NO | Replaced by newer calc | N/A (ops) | NO (must not masquerade) |
| FINALIZED | Official Term Result version | YES | NO (content frozen) | YES via new finalize | YES |
| SUPERSEDED | Former official | Historical official | NO | Already superseded | Historical reads only |

---

## 3. Allowed Transitions

| From | To | Trigger (conceptual) | Actor class |
|------|----|----------------------|-------------|
| NOT_CALCULATED | CALCULATED | CalculateTermResult / rebuild | System / authorized |
| CALCULATED | CALCULATED | Recalculate (new ops version) | System / authorized |
| CALCULATED | FINALIZED | FinalizeTermResult | Elevated academic authority — **exact roles HDR** |
| FINALIZED | SUPERSEDED | Newer FINALIZED for same business identity | Elevated / system with audit |
| CALCULATED | STALE/FAILED markers | Grade change / tech failure | System |

---

## 4. Forbidden Transitions

| Transition | Why |
|------------|-----|
| FINALIZED → mutate fields in place | Violates DL-007 |
| SUPERSEDED → FINALIZED without lineage | Breaks audit |
| Any → hard delete official | Violates DL-015 |
| CALCULATED labeled as FINALIZED without finalize command | Violates TR-INV-012 |
| Cross-school finalize | Security fail-closed |

---

## 5. Official vs Operational Dual Current

**PROPOSED:**

- At most one **operational current** version per business identity  
- At most one **official current** (FINALIZED not superseded) per business identity  
- They may differ until finalize catches up  

Exact permission matrix for who may finalize:

```text
HUMAN DECISION REQUIRED
```

---

## 6. Interaction with Grade Correction

```text
FINALIZED Term Result v1
Grade corrected
Operational Term Result → STALE → CALCULATED v2 (ops)
Finalize → FINALIZED v2; v1 → SUPERSEDED
```

Issued transcripts (if any) follow HD-11 — **UNRESOLVED**; Term Result lifecycle does not auto-rewrite issued artifacts.

---

## 7. Term Calendar (HD-17)

Whether finalize is allowed only when term is closed/finalized:

```text
HUMAN DECISION REQUIRED (HD-17)
```

Architecture reserves a **binding slot** for term eligibility without inventing open/closed semantics.
