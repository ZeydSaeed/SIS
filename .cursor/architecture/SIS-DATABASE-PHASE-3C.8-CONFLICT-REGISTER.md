# SIS DATABASE — PHASE 3C.8  
# CONFLICT REGISTER & INVARIANT COVERAGE

**Document type:** GOVERNANCE  
**Date:** 2026-09-10  

```text
Do not delete stale docs in this phase — classify and recommend markers only.
```

---

## 1. Conflict Register

| CONFLICT ID | SOURCE A | SOURCE B | CONFLICT | AUTHORITATIVE SOURCE | ACTION REQUIRED |
|-------------|----------|----------|----------|----------------------|-----------------|
| C-3C8-01 | Blueprint `graduation.eligibility_rules.min_gpa` NOT NULL | Policy neutrality; HD-22 UNRESOLVED; GC-INV-037 | Sketch appears as mandatory GPA rule | Phase 3C.7–3C.8 + HD register | Mark blueprint **STALE / NON-AUTHORITATIVE**; never seed as policy |
| C-3C8-02 | Blueprint `min_credit_hours` / `required_subjects` | HD-21/15 UNRESOLVED | Invented thresholds | HD-21/15 + 3C.8 | Same — STALE marker |
| C-3C8-03 | Blueprint `graduation.records` flat (weak school/version/provenance) | 3C.7 versioned model + DL-019 | Incomplete historical model | Phase 3C.7 Data Model | Treat blueprint as **SUPERSEDED sketch** |
| C-3C8-04 | Blueprint `promotion.rules.min_gpa` | HD-41; Graduation eligibility | Risk of reading promo as graduation | HD-41 + DL-021 | Keep promotion separate; mark **not graduation policy** |
| C-3C8-05 | PHASE-D graduation table list | Empty LIVE schema + 3C.7 redesign | Planning doc looks like approved schema | 3C.7–3C.8 | **CURRENT BUT NON-AUTHORITATIVE** |
| C-3C8-06 | `StudentStatus::Graduated` LIVE | Graduation award SSOT risk | Status could be misread as truth | DL-022 / GC-INV-036 | Enforce projection-only in future impl |
| C-3C8-07 | Certificates `graduation_id` blueprint | Award identity redesign | FK target may change | 3C.7 Award model | **STALE sketch** — redesign at cert phase |
| C-3C8-08 | Older grade UNIQUE docs | LIVE 3B partial UNIQUE | Carry-forward doc conflict | LIVE 3B | Cleanup FUTURE (unchanged) |
| C-3C8-09 | Annual blueprint embeds gpa/rank | Separate GPA/Ranking/Graduation | Conflated payload | 3C.4–3C.7 | Already classified STALE in prior phases |

No conflict requires weakening DL-001…016.

---

## 2. Stale Blueprint Protection (recommended markers — not applied this phase)

Recommend future doc edits (authorization separate):

```text
graduation.eligibility_rules.min_gpa / credits / subjects
  → STALE / NON-AUTHORITATIVE — NOT ACADEMIC POLICY

graduation.records (blueprint)
  → SUPERSEDED by Phase 3C.7 logical model

promotion.rules.min_gpa
  → NOT GRADUATION POLICY (see HD-41)

PHASE-D graduation tables
  → PLANNING ONLY / NON-AUTHORITATIVE

certificates.graduation_id
  → SKETCH — pending Award identity
```

**This phase does not modify those files.**

---

## 3. Invariant Coverage vs HD-19…42

| Invariant | Related HD | Related DL | Status | Gap |
|-----------|------------|------------|--------|-----|
| GC-INV-001…005 | — | DL-018 | covered | — |
| GC-INV-006/007 | HD-19 | DL-017 | covered / HD open | Legal naming |
| GC-INV-008/009/022 | HD-35/36/37 | DL-019 | covered / HD open | Revoke policy values |
| GC-INV-010/042/043 | — | DL-011 | covered | RLS impl future |
| GC-INV-011…016 | HD-20 | DL-014/008 | partially | Policy content |
| GC-INV-017…019/045 | HD-20…30,18 | DL-020 | covered / HD open | Evidence rules |
| GC-INV-020 | HD-31 | — | not covered by values | **Roles undefined** |
| GC-INV-021/031/032 | HD-35 | — | covered (design) | — |
| GC-INV-023 | HD-35/36 | — | partially | Institutional path open |
| GC-INV-024 | HD-11 | DL-018 | covered | — |
| GC-INV-025 | HD-08…10 | DL-021 | covered | — |
| GC-INV-026 | HD-22 | DL-021 | partially | GPA gate open |
| GC-INV-027 | HD-41 | DL-021 | covered / HD open | Confirm |
| GC-INV-028/039 | HD-40 | DL-021 | covered / HD open | Confirm |
| GC-INV-029 | HD-35 | DL-019 | covered | — |
| GC-INV-030 | HD-39 | — | partially | Multi-program confirm |
| GC-INV-033…035 | HD-32/38 | — | partially | Award/publish open |
| GC-INV-036 | — | DL-022 | covered | — |
| GC-INV-037 | HD-22 | — | covered (rejection of blueprint) | — |
| GC-INV-038 | — | DL-021 | covered | — |
| GC-INV-040 | HD-33/34 | DL-017 | partially | Date semantics open |
| GC-INV-041 | HD-21 | — | covered (typed evidence) | — |
| GC-INV-044 | — | DL-009 | covered | — |
| GC-INV-046 | HD-35 | DL-019 | covered | — |
| GC-INV-047 | HD-32 | — | partially | Cert link later |
| GC-INV-048 | HD-31/35 | — | covered (forbid auto) | — |
| GC-INV-049 | HD-21 | — | covered | — |
| GC-INV-050 | HD-19…42 | — | covered (this phase) | — |

**Gaps are expected:** invariants encode safety; HDs still supply institutional content. No invariant edits in 3C.8.

---

## 4. Classification Legend for Docs

| Class | Meaning |
|-------|---------|
| AUTHORITATIVE | Approved locks / LIVE verified |
| CURRENT | Exists and used but not policy law |
| CURRENT BUT NON-AUTHORITATIVE | Planning/LIVE helper |
| STALE | Outdated sketch |
| SUPERSEDED | Replaced by later phase design |
| CONFLICTING | Contradicts authoritative |
| NON-AUTHORITATIVE | Must not drive policy |
