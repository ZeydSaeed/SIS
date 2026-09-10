# SIS DATABASE — PHASE 3C.8A  
# DECISION DEPENDENCY GRAPH

**Document type:** DECISION PREPARATION ONLY  
**Date:** 2026-09-10  

```text
Every edge requires justification. No decorative dependencies.
```

---

## 1. DL → Invariant → Schema spine

```text
DL-001 (Grades SSOT)
  ↓ justified: graduation cannot own scores
DL-018 / GC-INV-001…005
  ↓
Evidence consumption model (DL-020 / GC-INV-019)
  ↓
Completion evaluation
  ↓
Eligibility
  ↓
HD-31 Approval workflow
  ↓
HD-32 Award
  ↓
HD-38 Publication
  ↓
Transcript display (HD-11 consumer)
```

---

## 2. Identity chain

```text
HD-19 (Completion ≠ Graduation)
  ↓ justified: decides entity grain
identity model (CompletionOutcome vs GraduationAward)
  ↓
HD-39 (multi-program scope)
  ↓ justified: business key includes school+enrollment/program
logical schema identity
  ↓
physical schema / migration (future auth)
  ↓
evaluation scope
```

---

## 3. Policy → Engine chain

```text
HD-20 (eligibility framework)
  ↓ justified: defines which rules exist
HD-21 (required units)
  ↓
HD-22 (GPA gate?) ——conditional——► HD-01 / HD-15 / Rounding
  ↓
HD-23…30 (category rules as selected)
  ↓ justified: only categories chosen by HD-20
RequirementDefinition versions
  ↓
Evaluation engine
  ↓
Official CompletionOutcomeVersion
```

Upstream evidence quality:

```text
HD-04/05/06/07/18
  ↓ justified: grade/result eligibility inputs
EvidenceItem validity
  ↓
HD-24/25/26 (completion-specific treatments)
```

---

## 4. Correction / integrity chain

```text
DL-019 / GC-INV-008/009
  ↓ justified: immutability lock
HD-35 (correction after graduation)
  ↓ justified: defines impact behavior
correction semantics
  ↓
supersession / candidate re-eval
  ↓
HD-36 (revocation)
  ↓ justified: revoke vs supersede-only
historical integrity
  ↓
official outcome implementation (future)
  ↓
StudentStatus / Transcript impact projections
```

```text
HD-37 (retention years)
  ↓ justified: ops/legal duration only
retention metadata / archival
  (does NOT block eval engine)
```

---

## 5. Authority chain

```text
Machine EvaluateCompletion
  ↓
HD-31 (who may approve)
  ↓ justified: authz model
CQRS Approve* + SECURITY
  ↓
HD-32 (what award means)
  ↓
Award record
  ↓
HD-33 / HD-34 (date meanings)
  ↓
HD-38 (who may see / publish)
```

---

## 6. Separation edges (anti-coupling)

```text
HD-40 ──justified: prevent year-close → auto graduate──► Calendar workflows (independent)
HD-41 ──justified: Promotion ≠ Graduation──► promotion.rules (non-authoritative for grad)
DL-021 ──justified: no hidden GPA/rank/promo──► optional explicit pins only
HD-42 ──justified: packaging ≠ domain──► module delivery (P2; no schema block)
```

---

## 7. Workshop closure order (human)

```text
1. DL-017…022 ACCEPT/MODIFY (UNDECIDED → human)
2. HD-19 → HD-39
3. HD-35 → HD-36
4. parallel: HD-20, HD-31, HD-32
5. HD-21 → (HD-22…30 as needed)
6. HD-33/34, HD-40/41
7. HD-38 before publish features
8. HD-37 / HD-42 deferrable
THEN: separate implementation authorization (not 3C.8A)
```
