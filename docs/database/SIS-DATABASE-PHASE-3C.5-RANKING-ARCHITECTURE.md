# SIS DATABASE — PHASE 3C.5  
# RANKING ARCHITECTURE

**Document type:** AUDIT + ARCHITECTURE + DESIGN LOCK ONLY  
**Date:** 2026-09-10  
**Predecessor:** Phase 3C.4 Gate (`PASS WITH CONDITIONS`)  
**Status:** ARCHITECTURE DESIGNED — ranking academic policies remain UNRESOLVED  

```text
NO DDL · NO MIGRATIONS · NO APPLICATION CODE · NO RANKING ENGINE
IMPLEMENTATION AUTHORIZATION: NOT GRANTED
```

---

## 1. Mission Statement

```text
RANKING
```

is a **versioned, reproducible, school-isolated, policy-driven snapshot/projection** that orders eligible participants by a governed source metric for a defined population and scope.

### Core lock (DL-005)

```text
Ranking is NOT academic truth.
```

Ranking MUST NOT become a second SSOT for grades, Term/Annual Results, GPA, or academic status.

### Canonical dependency (LOCKED)

```text
exams.student_grades
  ↓
Term / Annual Results
  ↓
GPA or other approved ranking input metric
  ↓
Ranking Snapshot
```

**FORBIDDEN backward edges:** Ranking → grades / Annual / GPA / eligibility / academic truth.

**Consumers (allowed):** reports, dashboards, analytics, approved student/admin views — never as calculation SSOT.

---

## 2. Audit of Existing Ranking Mentions

| Location | Finding | Classification |
|----------|---------|----------------|
| Blueprint `term_results.rank_in_section` | Sketch column on term result | **Stale sketch** — not Ranking SSOT |
| Blueprint `annual_results.rank_in_section` / `rank_in_class` | Sketch columns | **Stale sketch** — embeds rank into annual; superseded by separate Ranking Snapshot (DL-005) |
| Blueprint `rank_in_directorate` (elsewhere) | Sketch | **Stale / example** |
| Phase 3C.0 HD-08…10, HD-14 | Explicit unresolved | **Authoritative unresolved** |
| Phase 3C.1 Policy Catalog §5.9 | Scope/tie/privacy **examples only** | **Authoritative catalog slots** — not selected policy |
| Phase 3C.2–3C.4 | Ranking as downstream consumer | **Authoritative design** |
| Optimization `RecommendationRanker` / percentiles | Infra optimization ranking | **Out of academic Results scope** — do not adopt as academic ranking policy |
| Intelligence simulation `ranking: [C,A,B]` | Simulation config | **Unrelated** |

```text
AUTHORITATIVE: DL-005 + Phase 3C.5 logical Ranking Snapshot model
NON-AUTHORITATIVE: blueprint rank_* columns as Ranking SSOT
```

Do not invent DDL to “fix” blueprint sketches in this phase.

---

## 3. Core Questions Answered

| # | Question | Architectural answer | Status |
|---|----------|----------------------|--------|
| 1 | What is ranked? | Eligible participants in a versioned population under a Ranking Identity | **LOCKED** concept |
| 2 | Population? | Explicit RankingPopulation definition (versioned) | Structure **LOCKED**; membership rules **HDR (HD-08)** |
| 3 | Source metric? | Governed input (often official GPA version, or other approved metric) | Metric selection **HDR** |
| 4 | Official GPA vs other? | Contract identifies exact source version; no internal GPA recalculation | **LOCKED** |
| 5 | Snapshot? | RankingVersion → Ranking Snapshot of participant results | **LOCKED** |
| 6 | Version ID? | Ranking Identity + monotonic ranking_version | **LOCKED** |
| 7 | Policy/calc pins? | Required on official snapshots (DL-008/014) | **LOCKED** |
| 8 | Provenance? | Source/policy/calc/tie/privacy fingerprints + input set | **LOCKED** |
| 9 | Rebuild? | From Ranking Identity + pins + source versions + eligibility + population + tie policy | **LOCKED** |
| 10 | Correction? | Outbox → impact → new version → supersede | **LOCKED** |
| 11 | Ties? | Representable via tie group/key/policy version — policy **HDR (HD-09)** | Structure **LOCKED** |
| 12 | Publication vs calculation? | Separated (calc ≠ publish ≠ view) | **LOCKED** |
| 13 | Privacy? | Policy-controlled visibility layers — **HDR (HD-10)** | Structure **LOCKED** |
| 14 | School isolation? | SchoolContext → … → RLS → FORCE RLS; fail closed | **LOCKED** |
| 15 | Ops vs official? | Dual currents; ops must not masquerade | **LOCKED** |
| 16 | Determinism? | Same approved inputs ⇒ equivalent snapshot (DL-016) | **LOCKED** |
| 17 | History? | Official immutable; supersede not overwrite (DL-007/015) | **LOCKED** |
| 18 | Ranking fail vs Annual/GPA? | Non-blocking by default — **HDR (HD-14)** for any coupling | **LOCKED** default architecture |
| 19 | Scale? | Performance blueprint only; no premature partition/index | **LOCKED** |
| 20 | Policy evolution? | New versions under new pins; V1 preserved | **LOCKED** |

---

## 4. Ranking Identity (PROPOSED)

```text
(
  school_id,
  ranking_scope,           -- scope code; official set HDR (HD-08)
  scope_anchor_type,       -- typed discriminator (NOT untyped polymorphic)
  scope_anchor_id,         -- meaningful only with type
  ranking_period_type,     -- e.g. term | academic_year | custom — values HDR
  ranking_period_id,       -- typed with period type
  population_definition_id -- versioned population definition
)
```

**CRITICAL:** Never store an ambiguous `scope_anchor_id` without `scope_anchor_type` (or equivalent strongly typed design).

Examples of scopes (NOT selected): section, class, program, school, cohort, academic year, campus, grade level, specialization, enrollment group, national/regional.

---

## 5. Ranking Input Dataset

Reconstructible conceptual set:

| Element | Role |
|---------|------|
| Ranking subject / participant identity | Who is ranked (enrollment preferred; student denorm OK) |
| Source result / GPA identity + version | Exact consumed version |
| Scope membership | In/out of population |
| Eligibility state | Distinct layers (see §8) |
| Inclusion / exclusion + reason | Deterministic codes — values **HDR** |
| Source metric value + metric version | No invented scale |
| Policy / calculation / tie / rounding / privacy version pins | As applicable |
| Source fingerprint participation | Rebuild |

**Does NOT assume:** 4.0 GPA, percentage ranking, credit weighting, particular grading scale.

Ranking **must never recalculate GPA internally** — consume pinned source version only.

---

## 6. Versioning & Dual Current

```text
Ranking Identity → Ranking Version → Ranking Snapshot
```

| Concept | Rule |
|---------|------|
| Monotonic `ranking_version` | Per identity |
| Operational current | Recalculable, eventual (DL-013) |
| Official current | Finalize required; immutable |
| Superseded | Retained with lineage |
| Equivalence | Fingerprint + semantic participant ordering under pinned policies |

New calculation MUST NOT silently overwrite official history.

---

## 7. Lifecycle / Boundaries

```text
NOT_CALCULATED → CALCULATED → FINALIZED → SUPERSEDED
```

(+ optional STALE / FAILED / INELIGIBLE / BLOCKED_MISSING_POLICY markers)

**FINALIZED ranking does NOT imply:** Annual FINALIZED, GPA FINALIZED, transcript issued, graduation completed, academic year closed.

**Annual/GPA FINALIZED does NOT imply:** Ranking FINALIZED (unless future HD-14 policy says otherwise — currently UNRESOLVED; default = independent).

---

## 8. Eligibility Layers (do not collapse)

1. Population existence  
2. Scope membership  
3. Data validity  
4. Dataset completeness  
5. Academic eligibility  
6. Ranking eligibility  
7. Metric contribution / presence  

Each exclusion has a deterministic reason code (catalog **HDR**).

Depends on: HD-04, HD-05, HD-06, HD-07, HD-18, and metric deps HD-01/02/15/Rounding when metric is GPA-based.

---

## 9. Tie Architecture (HD-09)

Represent without selecting policy:

- `rank_position` (policy-defined meaning)  
- `tie_group_id`  
- `tie_key` / equal-metric marker  
- `tie_policy_version`  

Historical snapshots **must** pin which tie policy was used. Competition/dense/ordinal/average/GPA-breakers = **examples only — HDR**.

---

## 10. Privacy Architecture (HD-10)

Separate:

| Layer | Meaning |
|-------|---------|
| Calculation visibility | Who may run/see calc internals |
| Publication visibility | Whether snapshot is published |
| Individual participant visibility | Own rank / band / percentile exposure |
| Administrative visibility | Staff/registrar views |
| Peer visibility | Seeing others’ ranks — **default deny until policy** (architecture recommendation only, not locked product rule) |

Publication policy version pinned on published snapshots. Do not expose peer ranking by default in design guidance. Final policy = **HDR**.

Permission design slot: `results.view_ranking` (from 3C.1) — not implemented.

---

## 11. School Isolation & Security

```text
SchoolContext → Command/Query → Ranking Scope → Composite Identity → RLS → FORCE RLS
```

Default: **FAIL CLOSED**. No cross-school ranking without explicit future approved policy + security model.

Prevent: cross-school access/leakage, unauthorized publish, official mutation, hard delete, stale publish, unapproved policy/calc substitution, scope confusion, privacy bypass.

---

## 12. CQRS (conceptual)

**Commands:** `CalculateRanking`, `RebuildRanking`, `FinalizeRanking`, `SupersedeRanking`, `PublishRanking`  
**Queries:** `GetCurrentRanking`, `GetOfficialRanking`, `GetParticipantRank`, `GetRankingHistory`, `GetRankingSnapshot`, provenance/explain variants  

Distinguish: command-side version control · read projections · publication projection. No handlers in this phase.

---

## 13. Concurrency / Idempotency / Fingerprints (four mechanisms)

| Mechanism | Purpose |
|-----------|---------|
| **Idempotency** | Duplicate job/event does not create unintended duplicate official snapshots |
| **Concurrency control** | Serialize per Ranking Identity (FUTURE IMPLEMENTATION) |
| **Version conflict detection** | Stale writers cannot corrupt lineage |
| **Fingerprint equivalence** | Detect unchanged logical input/result — **not** sole idempotency |

---

## 14. Performance Blueprint (no indexes/partitions)

Access: by school, period, scope, participant, current/official/history, rebuild, correction, publication, large cohorts, multi-school, long retention.

```text
NO PARTITIONING INITIALLY (DL-010)
NO GUESSED INDEXES
```

Physical indexes later from measured query patterns + EXPLAIN ANALYZE. Revisit partitioning only with evidence (row growth, vacuum, latency).

---

## 15. Failure Model (classes)

| Scenario | Behavior class |
|----------|----------------|
| Missing GPA/source metric | Blocking for official — no invent |
| Incomplete population | ACADEMIC / HDR (HD-18) |
| Missing policy/calc/tie/privacy versions | Blocking — no silent fallback |
| Source version superseded mid-calc | Retry / re-read; no partial official |
| Concurrent rebuild | Serialize / conflict |
| Duplicate job | Idempotent |
| Partial calculation | Fail closed for official |
| Failed publication | Ranking calc may exist; publish fails separately |
| Privacy-policy mismatch | Fail closed on publish/view |
| School-context failure | **Security fail closed** |
| Fingerprint mismatch / timeout | Blocking / retryable / human-review |

**No partial official ranking. No silent older-policy fallback. No silent GPA substitution.**

---

## 16. Boundaries

### Annual Results
```text
Annual → GPA → Ranking
```
Ranking **not required** for Annual finalize. Ranking failure **does not** invalidate Annual/GPA/year closure by default (**HD-14 unresolved**; architecture default = independent).

### GPA
Consume pinned GPA (or other approved) version. Never modify GPA. Never recalculate GPA inside ranking.

### Transcript
Separate consumer (DL-006). Ranking not required for transcript unless future transcript policy says so. No transcript fields here.

---

## 17. Traceability (summary)

| Item | Status |
|------|--------|
| DL-001…016 | **Preserved** |
| HD-08, HD-09, HD-10, HD-14 | **Unresolved** (structure designed) |
| HD-01, HD-02, HD-04…07, HD-15, HD-18, Rounding | **Unresolved dependencies** when metric/eligibility require them |
| HD-11, HD-12, HD-16 | Transcript — boundary only |

---

## 18. Architectural Q&A Gate

| Check | Answer |
|-------|--------|
| Ranking non-SSOT? | **YES** |
| Scope/ties/privacy invented? | **NO** |
| Typed scope anchors? | **YES** (required) |
| Ops/official + rebuild + provenance? | **YES** |
| Four concurrency mechanisms separated? | **YES** |
| Implementation performed? | **NO** |
