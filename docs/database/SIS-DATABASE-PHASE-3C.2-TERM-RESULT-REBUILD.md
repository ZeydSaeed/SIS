# SIS DATABASE — PHASE 3C.2  
# TERM RESULT REBUILD ARCHITECTURE

**Document type:** DESIGN ONLY  
**Date:** 2026-09-10  

```text
NO JOBS · NO CODE · NO DDL
```

---

## 1. Rebuild Goal

Support:

```text
REBUILD TERM RESULT
```

such that for a given business identity (and optional target policy/calculation versions), the system can determine:

| Question | Answer source |
|----------|---------------|
| What grades? | LIVE `exams.student_grades` (+ historical rows as needed for selection) |
| What academic structure? | year, term, enrollment, exam graph, subject |
| What policy? | Published policy version ids (pinned or “effective as-of”) |
| What calculation version? | Explicit engine version |
| What eligibility boundary? | HD-04 + HD-18 policy versions / outcomes |
| What fingerprint? | Recomputed per contract |
| What result version? | New version if material change; else idempotent no-op |

---

## 2. Authoritative Input Bundle

```text
student_grades (SSOT)
+ academic structure
+ immutable policy versions
+ calculation version
+ eligibility boundary
```

**Not sufficient alone:** processed outbox history (DL-009).

Outbox may **trigger** rebuild/recalc; rebuild **executes** from the bundle above.

---

## 3. Rebuild Modes (conceptual)

| Mode | Purpose |
|------|---------|
| Operational refresh | Produce/replace CALCULATED current |
| Official rebuild + finalize | Produce new FINALIZED; supersede prior official if changed |
| Verification rebuild | Compare fingerprint/semantics to stored official (detect drift) |

Verification mismatch ⇒ technical/governance alert — **not** silent overwrite.

---

## 4. Idempotency

If rebuild inputs equal prior official fingerprint + same policy/calc versions:

```text
NO UNCONTROLLED DUPLICATE OFFICIAL CURRENT
```

Distinguish:

| Concept | Meaning |
|---------|---------|
| Recalculation | Re-run engine |
| New academic truth | Material input/policy/calc change ⇒ new version |
| Repeated event processing | Same trigger twice ⇒ idempotent |

---

## 5. Algorithm Shape (non-executable)

Align with Phase 3C.1 Calculation Contract stages:

1. Resolve business identity + school context (fail closed)  
2. Load candidate grades for enrollment/term/subject  
3. Apply retake policy (HD-06 — **HDR**)  
4. Apply status eligibility (HD-04 — **HDR**)  
5. Apply absence/missing treatments (HD-05 — **HDR**)  
6. Apply weighting/rounding (HD-03 / Rounding — **HDR**)  
7. Emit outcome + sources + fingerprint  
8. Persist as CALCULATED and/or FINALIZED per command  

Where policy unresolved, rebuild for **official** finalize must refuse with `POLICY_MISSING` / HDR — not invent values.

---

## 6. Concurrency with Correction

| Scenario | Architectural stance |
|----------|----------------------|
| Rebuild during grade correction | Serialize per business identity (FUTURE IMPLEMENTATION) |
| Finalize during rebuild | One official current; fingerprint decides identity of version |
| Duplicate outbox | Idempotent trigger |

---

## 7. Golden Vector Mapping (Term Result)

| Vector | Identity | Versioning | Eligibility | Weight | Absence | Retake | Correction | Rebuild | Policy Δ | Calc Δ |
|--------|----------|------------|-------------|--------|---------|--------|------------|---------|----------|--------|
| GV-01 | Y | Y | Y | Y | | | | Y | | |
| GV-02 | Y | | Y | | Y | | | Y | | |
| GV-03 | Y | | Y | | missing | | | Y | | |
| GV-04 | Y | | | Y | | | | Y | | |
| GV-05 | | | | Y | | | | | | |
| GV-06 | Y | | | | | Y | | Y | | |
| GV-07 | Y | | Y | | | | | Y | | |
| GV-08/09 | Y | | Y | | | | | Y | | |
| GV-10 | | | | | boundary | | | | | |
| GV-11 | | | | | | | | Y | | rounding |
| GV-12 | | Y | | | | | | Y | Y | |
| GV-13 | | Y | | | | | | Y | | Y |
| GV-14 | Y | Y | | | | | Y | Y | | |
| GV-15 | Y | Y | Y | Y | Y | Y | | Y | Y | Y |

Expected numeric outputs remain **HDR** where policies unresolved.

---

## 8. Failure Outcomes

| Condition | Class |
|-----------|-------|
| Missing school context | SECURITY FAILURE |
| Cross-school grade/enrollment | SECURITY FAILURE |
| Missing published policy | TECHNICAL/ACADEMIC block — no invent |
| Fingerprint mismatch on verify | TECHNICAL / governance |
| Dataset incomplete | ACADEMIC INELIGIBILITY (HD-18) |
| Duplicate finalize | IDEMPOTENT / conflict — no dual official current |
