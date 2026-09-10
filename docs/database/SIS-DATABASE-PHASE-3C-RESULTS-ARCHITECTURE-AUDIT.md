# SIS DATABASE PHASE 3C — RESULTS ARCHITECTURE AUDIT & DESIGN

**Document type:** AUDIT + DESIGN LOCKS + IMPLEMENTATION PLAN ONLY  
**Date:** 2026-09-10  
**Authoritative foundations:** Phase 3A (Exam Foundation) · Phase 3B (`exams.student_grades`) · Phase 3B.1 (Grade Application Hardening)  
**Blueprint SSOT:** 87 objects (unchanged by this document)  
**Prior related plan:** `SIS-DATABASE-PHASE-3-ACADEMIC-CORE-AUDIT.md` (staged Option C: 3A→3B→3C→3D)

```text
THIS DOCUMENT DOES NOT AUTHORIZE IMPLEMENTATION.
NO DDL · NO MIGRATIONS · NO SCHEMA CHANGES · NO API · NO UI · NO CALCULATION CODE
```

---

## 1. Executive Summary

Phase 3C must answer how SIS derives, persists, versions, reproduces, audits, secures, and queries **Term Results, Annual Results, Transcripts, GPA, and Ranking** for 20+ years **without corrupting historical academic truth** and without treating `results.*` as a second grade ledger.

### Primary answer (design position)

| Concept | Role | Store? |
|---------|------|--------|
| Raw / authoritative exam score | **SSOT** | `exams.student_grades` (already LIVE) |
| Exam result | Alias of current/finalized grade row for a session | No separate table |
| Term result | **Versioned derived academic snapshot** (rebuildable from grades + pinned policy) | Yes — future `results.*` |
| Annual result | **Versioned derived year rollup** (from term results and/or grades under pinned policy) | Yes |
| GPA | **Versioned derived metric** pinned to calculation + grading policy versions | Yes (history) + optional live read projection |
| Ranking | **Versioned derived comparative projection** (not authoritative academic score) | Yes (scoped snapshots) |
| Transcript | **Hybrid:** live academic view + **immutable issued artifact** | Yes (issuance metadata + payload/hash) |

**Central invariant:** `exams.student_grades` remains the only authoritative score store. Results must never become a writable second SSOT for marks.

**Central historical rule:** Operational derived results may be recalculated from current grades; **issued transcripts and explicitly finalized official result versions must not silently mutate**. Corrections create **new versions / supersessions**, not unexplained in-place rewrites of history.

### Audit gate (preview)

```text
PASS WITH CONDITIONS
```

Architecture is viable and aligned with existing CQRS/Outbox/RLS governance, but **multiple human/business decisions** (GPA scale/formula, ranking ties/visibility, transcript immutability policy, official vs operational finalization) **block safe implementation** until approved.

```text
Phase 3C IMPLEMENTATION STATUS: NOT STARTED
Database changes: NONE
Migrations: NONE
API changes: NONE
UI changes: NONE
```

```text
Phase 3C implementation requires separate explicit human approval.
```

---

## 2. Human Approval Boundary

### Approved for this phase

- Repository/codebase inspection  
- Architecture / domain / dependency / performance / security analysis  
- Design alternatives, Decision Matrix, Design Locks (PROPOSED)  
- ADR recommendations  
- Implementation plan, test plan, migration plan, rollback strategy  
- Risk analysis and production-readiness criteria  
- **Documentation only** (`docs/database/SIS-DATABASE-PHASE-3C-RESULTS-ARCHITECTURE-AUDIT.md`)

### Explicitly NOT approved

- Migrations, tables, indexes, constraints, triggers, RLS, partitions, MVs  
- Database writes / seeds / live `sis` or `sis_test` schema changes  
- API / commands / queries / UI / calculation / GPA / ranking / transcript code  
- Any Phase 3C application implementation  

If an existing structure must change for 3C: **document as proposed future migration — do not change it now.**

---

## 3. Current Architecture (evidence)

### Pipeline in production/design reality

```text
Admission → Enrollment → Exam Foundation (3A)
  → Student Grades DB (3B)
  → Grade Application Layer (3B.1)
  → [RESULTS — NOT STARTED]
```

### What exists (code/schema evidence)

| Layer | Evidence |
|-------|----------|
| Academic calendar | `academic.academic_years`, `academic.terms` (`term_order`, unique `(academic_year_id, code)`) LIVE |
| Exams | `exams.exam_types` (incl. `weight_percentage`), `exams`, `exam_sessions`, `exam_enrollments` LIVE |
| Grades SSOT | Partitioned `exams.student_grades` + FORCE RLS + VOID+INSERT corrections LIVE |
| Grade app | Enter/Correct/Void/Finalize + queries + Policy + Outbox events LIVE |
| Results schema tables | **0 tables** under `results` (schema may exist empty; blueprint defines 3 tables) |
| Outbox | Grade events rehydrated; `ProcessOutboxJob` has **no Results consumers** (events currently marked processed without projection work) |
| Curriculum | `subjects.credit_hours` nullable; `max_grade`/`pass_grade` present; no grading-policy version table |
| Promotion | Blueprint only (`promotion.rules` with `min_gpa`) — not Phase 3C implement scope, but consumer of annual/GPA |

### Blueprint sketch (not implemented; incomplete for 3C safety)

`results.term_results`, `results.annual_results`, `results.transcripts` exist in `database-blueprint.md` as thin sketches:

- Missing: `school_id`, year denorm on term rows, versioning, status/lifecycle, policy/calc version pins, source grade refs, supersession, RLS notes  
- Ranking embedded as `rank_in_*` columns (no separate ranking entity)  
- GPA only on `annual_results`  
- Transcript = file metadata + number (aligned with SHA-256 integrity docs)

**This audit does not treat the blueprint sketch as locked DDL.** It is a starting sketch requiring enrichment before any migration.

---

## 4. Existing Database Analysis

### Academic term model — ALREADY PRESENT

`academic.terms`:

| Field | Role |
|-------|------|
| `academic_year_id` | Year ownership |
| `code` / `name` | Human labels |
| `start_date` / `end_date` | Calendar bounds |
| `term_order` | Ordering within year |
| `status` | Active/inactive (SMALLINT; domain VO not fully productized) |

`exams.exams.term_id` already binds assessments to a term. Therefore Phase 3C does **not** require inventing academic terms from scratch.

**Remaining gaps (document only):**

- No explicit “term closed / results locked” academic calendar state separate from `status`  
- No school-specific term calendars (terms appear global per year)  
- Overlap/gap validation between terms is not evidenced as DB CHECKs beyond date fields  
- Cross-year terms: model forbids (FK to one year) — good  

**PROPOSED future (not now):** optional `term_lifecycle` / closed flags used by Results finalization — prefer academic calendar enrichment over inventing parallel calendars inside Results.

### Grade → term linkage path (authoritative join)

```text
student_grades
  → exam_session_id → exam_sessions
  → exam_id → exams.term_id + academic_year_id + school_id
  → subject_id (denorm on grade)
  → enrollment_id / student_id (denorm)
```

Term aggregation **can** be rebuilt from grades + exam graph without storing scores again.

### Scale baseline (capacity docs)

| Variable | Baseline reference |
|----------|--------------------|
| Students | 45,000 |
| Schools | 20 |
| Grades / student / year | ~60 |
| Retention | 10–20 years design horizon |

Rough grade volume: `45k × 60 ≈ 2.7M grade rows/year` (orders of magnitude below attendance). Results volumes are lower still (see §25).

---

## 5. Existing Grade SSOT Analysis

### Authoritative store

```text
exams.student_grades
```

Properties already locked by Phase 3B/3B.1:

- Composite identity `(id, academic_year_id)`  
- `is_current` + partial unique per exam enrollment/year  
- VOID+INSERT corrections; immutable `correction_of_grade_id`  
- `max_score` snapshot from session  
- Status: Draft / Entered / Submitted / Finalized / Voided  
- No hard DELETE; FORCE RLS; school_id denorm  
- Events: `StudentGradeEntered|Corrected|Voided|Finalized`  

### Implications for Results

1. Results consumers must prefer **`is_current = true`** grades (and usually **Finalized** for official calc — **DECISION REQUIRED** whether Entered counts for draft term reports).  
2. Correction automatically changes “current” identity → derived results become **stale** until recalculated.  
3. Historical voided rows remain for audit/rebuild of *what was current at time T* only if Results versions pin **source grade ids** (not merely “latest current”).  
4. **Outbox alone is insufficient for long-term rebuild** once `ProcessOutboxJob` marks messages processed without projection side effects. Rebuild SSOT = **grades table + policy/calc catalogs**, optionally assisted by outbox for incremental updates.

### Incompatibility check with 3B/3B.1

| Topic | Compatible? | Note |
|-------|-------------|------|
| Using grades as SSOT | YES | Matches Phase 3 Academic Core audit |
| Async Results consumers | YES | Fits Outbox ADR-013; requires new bridges/handlers |
| Silent overwrite of finalized grades | N/A | Already forbidden |
| Need to change grade schema for 3C | **No hard blocker found** | Enrichments optional (e.g. expose finalized-only query ports) |
| Current outbox “process = mark done” for grades | **Design gap for 3C** | Not a 3B bug; Results must not rely solely on already-processed outbox for rebuild |

**No Phase 3B/3B.1 redesign required** for 3C to proceed after decisions. Documented gaps are **Results-side** and **consumer/replay** design.

---

## 6. Term Result Analysis

### What a Term Result must mean

Per enrollment × term × subject (blueprint uniqueness) **or** enrollment × term header + subject lines — see alternatives.

Inputs available today:

- Current (and historical) grades for sessions under exams in that term  
- `exam_types.weight_percentage`  
- Session `max_grade` / grade `max_score` snapshot  
- Absence flags  
- Subject pass thresholds (`subjects.pass_grade` / session `pass_grade`)  

### Alternatives

| Option | Description | Pros | Cons |
|--------|-------------|------|------|
| **A** Direct copy of grade rows | Duplicate scores into results | Simple | Second SSOT; correction nightmare |
| **B** Pure calculated aggregate (no store) | Always compute at read | Always fresh | Slow ranking/transcripts; no official freeze |
| **C** Versioned academic snapshot | Persist calculated totals + pins + version | Official freeze + rebuild | More schema |
| **D** Projection/read model only | Cache table, no official semantics | Fast reads | Weak for certificates/promotion |
| **E Hybrid** | Versioned official snapshots + operational “current” projection | Serves both truth & UX | Complexity |

### Recommendation — **Option E (Hybrid)** with Option C core

- **Operational current term result:** rebuildable projection / latest version marked `is_current`  
- **Official term result:** explicitly **Finalized** version that does not auto-mutate; corrections produce **new version** or mark stale + require re-finalize  

**Reject A** (second ledger). **Reject B-only** (cannot support issued docs / promotion gates at scale). **D-only** insufficient for academic finalization.

### Subject-line model (PROPOSED)

Prefer:

```text
results.term_result_sets (header: enrollment, term, school, year, status, versions…)
results.term_result_lines (subject totals, pass/fail, components summary)
```

Blueprint’s single `term_results` table is acceptable as an **MVP line table** if header metadata is denormalized carefully — but versioning is awkward in one flat unique `(enrollment, term, subject)` without version column.

**PROPOSED enrichment over blueprint:** add `school_id`, `academic_year_id`, `version`, `is_current`, `status`, `grading_policy_version_id`, `calculation_version`, `supersedes_id`, `calculated_at`, source fingerprint / grade-id set hash.

### Assessment components

Multiple exam types per term (midterm/final) should aggregate via **weights** (`exam_types.weight_percentage`) under a pinned policy. Exact weight normalization when weights ≠ 100 is **DECISION REQUIRED**.

---

## 7. Annual Result Analysis

### Alternatives

| Source | Assessment |
|--------|------------|
| Aggregate finalized term results only | Clean layering; fails if a term missing |
| Aggregate directly from `student_grades` for year | Rebuildable even if term projections corrupt |
| Independent manual annual entry | Forbidden second SSOT |

### Recommendation

**Authoritative calculation source:** `student_grades` (+ exam/term graph + pinned policies).  
**Term results:** preferred incremental input when present and consistent; **annual rebuild must be able to ignore corrupt term projections and recompute from grades**.

Annual row semantics (PROPOSED):

- One **current** annual result per enrollment/year (versioned history retained)  
- Fields: pass/fail summary, credits attempted/earned (if credit model approved), GPA (if in scope), ranks (or FK to ranking snapshot), `final_status`  
- Promotion module later **reads** finalized annual results — does not recalculate grades  

Missing term / incomplete year / withdrawn subjects: **DECISION REQUIRED** (block finalize vs allow Incomplete status).

---

## 8. GPA Analysis

### Evidence in repo

| Finding | Implication |
|---------|-------------|
| `data-quality-rules.md`: GPA “0–4 or 0–100” | Scale **undecided** |
| `annual_results.gpa NUMERIC(4,2)` | Storage sketch only |
| `subjects.credit_hours` nullable | Credit-hour GPA possible but not mandated |
| `exam_types.weight_percentage` | Weighted term components exist |
| No letter-grade conversion table | Letter/GPA maps missing |
| `promotion.rules.min_gpa` | Downstream consumer expects *some* GPA |

### GPA models to support (design space — not all required day one)

| Model | Inputs | Status |
|-------|--------|--------|
| Percentage average | Scores / max | Feasible now |
| Weighted subject average | Weights from curriculum/policy | Needs policy |
| Credit-hour GPA | credits × grade points | Needs credit completeness + conversion |
| Term GPA | Term snapshot | Derived |
| Annual GPA | Year snapshot | Derived |
| Cumulative GPA | Multi-year | Needs retention rules |

### Formula dimensions (all **DECISION REQUIRED** unless product specifies)

- Scale (4.0 vs 100 vs school-specific)  
- Rounding / precision  
- Include/exclude: fail, absent, exempt, withdrawn, incomplete, electives, repeated subjects  
- Repeated exam: last / best / average  
- Correction: GPA of official version vs live operational GPA  

### Storage recommendation

```text
BOTH:
  - Authoritative calculation function (pure, versioned)
  - Persisted versioned GPA rows / fields on annual (and optionally term) results
```

Dynamic-only GPA is unsafe for promotion/transcript reproducibility. Persist-only without rebuildability is unsafe for corruption recovery.

**Do not invent a national formula in code until human locks it.**

---

## 9. Ranking Analysis

Ranking is **comparative social/academic metadata**, not a grade.

### Blueprint today

`rank_in_section` on term_results; `rank_in_section` / `rank_in_class` on annual_results.

### Ranking scopes to design for

| Scope | Typical key |
|-------|-------------|
| Section | `(school, year, term?, section_id)` |
| Class / grade level | `(school, year, class_id)` |
| School cohort | `(school, year)` |
| Program/specialization | optional |

### Tie policies (DO NOT SELECT without evidence)

| Policy | Example 95,95,94 → |
|--------|---------------------|
| Competition (1224) | 1,1,3 |
| Dense (1223) | 1,1,2 |
| Ordinal (unique force) | 1,2,3 arbitrary |
| No rank on ties | null / “T-1” |

**DECISION REQUIRED.**

### Storage recommendation

Prefer **versioned ranking snapshots** (header + entries) for class/school boards, rather than only mutating `rank_in_*` on every student row (write amplification + weak audit). Embedding rank on annual/term lines remains OK as a **denormalized copy of a snapshot version**.

### Security (design only)

Ranking may reveal peers’ performance.

| Actor | Suggested default (PROPOSED, needs approval) |
|-------|-----------------------------------------------|
| Student | Own rank only (or none) — **DECISION REQUIRED** |
| Teacher | Own section/class ranks within school |
| Registrar / academic admin | School-wide |
| Cross-school | Forbidden (RLS) |

Permissions sketch: `results.view_ranking` separate from `results.view`.

---

## 10. Transcript Analysis

### Critical question

> If a grade is corrected five years after the student left school, should an already-issued transcript change automatically?

**This cannot be inferred from code.** Marked **DECISION REQUIRED**.

### Architecture that supports both academic truth and document reproducibility

**Hybrid (PROPOSED):**

| Artifact | Behavior |
|----------|----------|
| Live academic transcript view | Reflects current grades / current official results |
| Issued transcript | Immutable snapshot: payload or file + `file_hash` + version + policy/calc pins + source result versions |
| Later correction | Updates live view; issued doc **unchanged**; optional **Superseding transcript** issued intentionally |

Aligned with existing `security-audit-resilience.md` SHA-256 guidance and blueprint `storage_key` / `file_hash`.

### Issuance lifecycle (conceptual)

```text
Draft generation → Issue (number, issuer, timestamp, hash)
  → Revoke (reason, actor)
  → Supersede (new number/version links previous)
```

No DELETE of issued metadata.

---

## 11. Historical Truth

### Scenario

```text
Grade finalized = 75
Later correction → 82 (VOID+INSERT; new current)
```

| Consumer | Expected behavior (PROPOSED) |
|----------|------------------------------|
| Live term/annual/GPA/ranking operational view | Become **stale** → async/explicit recalculation → new current derived version |
| Previously **Finalized** official term/annual version | Remain readable as historical version; not silently rewritten |
| Issued transcript citing old version | Unchanged |
| Audit | GradeCorrected + ResultsRecalculated / Superseded events |

**Forbidden:** unexplained mutation of historical official rows without version/supersede trail.

---

## 12. Correction Propagation

### Event flow (conceptual — not implemented)

```text
student_grades (SSOT)
      ↓
Outbox: StudentGradeEntered|Corrected|Voided|Finalized
      ↓
Results Invalidation / Recalculation Worker (future)
      ↓
Term Result versions
      ↓
Annual Result versions
      ↓
GPA versions
      ↓
Ranking snapshots
      ↓
Transcript live projection (issued artifacts untouched)
```

### Processing model (PROPOSED)

| Concern | Choice |
|---------|--------|
| Sync vs async | **Async** for projection (ADR-008 eventual summaries; ADR-013 outbox) |
| Official finalize | **Explicit command** (registrar), not silent |
| Ordering | Per `(school_id, enrollment_id, academic_year_id)` serializable processing key |
| Duplicates | Idempotent handlers keyed by event id / grade identity + calc version |
| Replay | Rebuild from grades preferred; outbox incremental only while messages unprocessed or via dedicated rebuild job |
| Failure | Retry with attempts; dead-letter; mark derived `stale`/`failed`; operator visibility |
| Consistency | See §22 |

**Important evidence:** today’s `ProcessOutboxJob` marks unknown/handled enrollment events processed; grade events rehydrate but have **no Results bridge** — still marked processed. Therefore **do not depend on historical outbox rows for rebuild**. Use grades table.

---

## 13. Grading Policy Versioning

### Problem

If letter boundaries / weights / GPA conversion change in 2029, 2026 results must remain explainable.

### Recommendation (PROPOSED catalog — future migration)

```text
results.grading_policies
results.grading_policy_versions  (immutable once published)
  - letter bands, pass rules, weight rules, rounding, GPA conversion, ranking tie policy refs
```

Every official result / issued transcript stores:

- `grading_policy_version_id`  
- `calculation_version` (code algorithm id)  

Changing live school settings must **not** rewrite historical official results.

`academic.system_settings` JSON exists but is **insufficient** alone (no immutability/version publish workflow).

---

## 14. Calculation Versioning

PROPOSED:

| Field | Purpose |
|-------|---------|
| `calculation_version` | Semver or integer of algorithm build |
| `calculated_at` | When |
| `calculated_by` | User or `system:` actor |
| `source_fingerprint` | Hash of contributing current grade ids + scores + weights |
| `status` | Draft / Current / Finalized / Superseded / Stale / Failed |

Enables explainability when algorithms improve.

---

## 15. Rebuildability

### Can `student_grades` rebuild everything?

| Output | Rebuildable from grades? | Extra immutable inputs required |
|--------|--------------------------|----------------------------------|
| Term aggregates | YES | Exam/term graph, weights, policy version |
| Annual aggregates | YES | Same |
| GPA | YES | GPA formula + policy version |
| Ranking | YES | Scope + tie policy + cohort membership (enrollment class/section) |
| Issued transcript PDF/file | **NO** (must keep artifact) | Stored file + hash + issuance record |
| Live transcript view | YES | Same as annual/term |

**Additional immutable sources to introduce before relying on rebuild:** published grading/calculation policy versions; issuance blobs.

---

## 16. Event Architecture

### Reuse

- Existing transactional outbox (`ADR-013`)  
- No second bus  

### Future domain/integration events (conceptual)

```text
TermResultCalculated / TermResultFinalized / TermResultSuperseded
AnnualResultCalculated / AnnualResultFinalized
GpaCalculated
RankingSnapshotCalculated
TranscriptIssued / TranscriptRevoked / TranscriptSuperseded
ResultsProjectionStale  (optional ops signal)
```

### Classification

| Type | Role |
|------|------|
| Domain events | Results lifecycle inside Results context |
| Integration events | Notify promotion/certificates/comms later |
| Audit events | `SecurityAuditLogger` (actor, reason, correlation) |
| Projection triggers | Outbox → workers |

Grade events remain owned by Exams; Results **consumes** via ACL/read ports — does not write grades.

---

## 17. CQRS Design

### Bounded context recommendation

**Single `Results` bounded context** with internal modules (Term, Annual, GPA, Ranking, Transcript).  

Reject splitting into separately deployable GPA/Ranking/Transcript contexts now (conflicts with ADR-008 monolith CQRS-lite; increases consistency cost).

### Commands worth having (future)

| Command | Why |
|---------|-----|
| `CalculateTermResult` | Explicit / job-driven recompute |
| `FinalizeTermResult` | Official freeze |
| `RecalculateTermResult` | After correction / policy fix |
| `CalculateAnnualResult` / `FinalizeAnnualResult` | Same pattern |
| `CalculateGpa` | May fold into annual calc |
| `CalculateRankingSnapshot` | Cohort board |
| `IssueTranscript` / `SupersedeTranscript` / `RevokeTranscript` | Document control |
| Bulk recalculate* | Ops — queued, not HTTP-inline |

Avoid God `ResultsService`. Avoid auto-command on every HTTP grade write inside Exams handlers (keeps Exams free of Results coupling) — prefer outbox consumer + explicit finalize.

### Queries worth having

`GetTermResult`, `GetAnnualResult`, `GetStudentGpa`, `GetStudentTranscript` (live), `GetIssuedTranscript`, `GetClassRanking`, `GetSectionRanking`.

---

## 18. Security / RLS

### Permissions (design only)

```text
results.view
results.calculate          # or results.recalculate
results.finalize
results.issue_transcript
results.revoke_transcript
results.view_ranking
results.manage_policy      # publish grading policy versions — elevated
```

Map later to registrar / academic admin / teacher / viewer. Do not assume `grades.*` suffices.

### RLS classification

| Table class | RLS | FORCE | `school_id` denorm |
|-------------|-----|-------|--------------------|
| Term/annual results | YES | YES | **YES** (match grades/exams pattern) |
| Ranking snapshots | YES | YES | YES |
| Transcript issuance | YES | YES | YES (via student school or denorm) |
| Global grading policy catalog | Maybe shared publish model | Careful | School-scoped policies preferred |
| Calculation version registry | Global code enum/table | Optional | N/A |

Composite FKs: `(enrollment_id, school_id)`, `(term_id, academic_year_id)` where uniqueness supports them.

### Defense in depth

```text
Policy → SchoolContext → Handler → Composite FK → RLS → FORCE RLS
```

Same stack as Enrollment/Grades.

---

## 19. Normalization

| Concern | Guidance |
|---------|----------|
| 1NF–3NF | Store atomic subject lines; no JSON score maps as SSOT |
| Intentional denorm | `school_id`, `academic_year_id`, rank copies from snapshot |
| Forbidden duplication | Subject names, student names as authoritative; raw scores copied as editable masters |
| BCNF | Ranking entries reference snapshot header; avoid multi-cohort facts in one row |

Derived fields (`is_pass`, `gpa`, ranks) are allowed on snapshots **because they are version-pinned outputs**, not competing masters.

---

## 20. Keys / FKs

### PROPOSED natural keys

| Entity | Natural uniqueness (current version) |
|--------|--------------------------------------|
| Term result line | `(enrollment_id, term_id, subject_id, version)` or partial unique where `is_current` |
| Annual result | `(enrollment_id, academic_year_id, version)` / current partial unique |
| GPA | Often attributes of annual version; or `(enrollment_id, year, gpa_type, version)` |
| Ranking snapshot | `(school_id, academic_year_id, term_id?, scope_type, scope_id, version)` |
| Transcript issue | `transcript_number` globally unique; `(student_id, version)` |

Preserve school/year scoping — never assume student_id alone is tenant-safe.

PKs: BIGINT IDENTITY; partitioned parents must include partition key in identity if partitioned (lessons from grades).

---

## 21. Partitioning

| Table | Recommendation | Reason |
|-------|----------------|--------|
| `student_grades` | Already LIST `academic_year_id` | ADR-002 |
| Term results | **Defer partition**; revisit with measured growth | ~subjects × enrollments × terms ≪ attendance; optional LIST year later |
| Annual results | **No partition** initially | ~1 current row / enrollment / year |
| Ranking snapshots | **No partition** initially; archive old versions | Burst write on recalc |
| Transcripts metadata | **No partition** | Low volume |

Do **not** auto-partition every Results table. Prefer year filters + indexes first; partition when EXPLAIN/size evidence demands (adaptive governance).

Estimated order: term lines ~ `45k × ~15 subjects × ~2–3 terms ≈ 1–2M rows/year` upper bound — meaningful but not attendance-class.

---

## 22. Index Strategy (conceptual only)

| Query | Candidate indexes |
|-------|-------------------|
| Student term | `(school_id, enrollment_id, term_id)` |
| Student year / transcript live | `(school_id, student_id, academic_year_id)` |
| Class ranking board | `(school_id, academic_year_id, section_id/class_id, snapshot_version)` |
| Stale detection | `(school_id, status)` where status=Stale |
| Policy pin joins | `(grading_policy_version_id)` |

Trade-off: every current-unique partial index helps reads and hurts bulk recalc writes — acceptable if recalc is queued/batched.

---

## 23. Performance / 20-Year Scale

### Hot paths

- Teacher/registrar: single-student term/annual fetch (OLTP, primary)  
- Class ranking boards (heavier; snapshot)  
- Transcript generation (async job + object storage)  
- Year-end bulk recalculation (queue, throttle)

### Strategy

- Incremental recalc per enrollment on grade events  
- Snapshot ranking instead of live window functions on every page load  
- Read replica for large boards (ADR-006)  
- No MV required at day one; candidate later for directorate analytics  
- Heavy ops **never** in HTTP request (Constitution / sis-core)

### Intelligence layer (safe vs human)

| Automated (observe/recommend) | Human approval required |
|------------------------------|-------------------------|
| Stale projection detection | Grading policy publish |
| Recalc backlog alerts | Calculation rule changes |
| Slow query / index recommendations | Schema/partition changes |
| Anomaly: mass GPA shifts | Historical official corrections |
| Rebuild recommendation | Transcript legal policy changes |

Intelligence must **never auto-alter academic truth** (existing intelligence governance).

---

## 24. Consistency Model

| Object | Model | Rationale |
|--------|-------|-----------|
| Grade write | Strong (UoW + PG constraints) | Existing |
| Operational term/annual/GPA after grade event | **Eventually consistent** (seconds–minutes via outbox/queue) | ADR-008 |
| Official finalized result version | **Strong at finalize time**; thereafter immutable except supersede | Academic truth |
| Ranking boards | Eventual; snapshot consistent | Comparative UX |
| Issued transcript | Snapshot consistent / immutable | Legal reproducibility |
| Live transcript view | Eventual w.r.t grades | UX |

**SLA expectation (PROPOSED product default, needs approval):** operational GPA/rank refresh within outbox/queue SLO (≤ 1–5 minutes typical), not synchronous inside `EnterStudentGradeHandler`.

---

## 25. Failure Recovery

```text
Grade event → Term calc FAIL
```

1. Increment attempts; retry with backoff  
2. Leave prior current result; set enrollment/year marker `results_stale=true` (or result status Stale)  
3. Dead-letter after N failures; ops dashboard  
4. Manual `Recalculate*` or rebuild job  
5. Do not mark academic year “final” while stale official paths pending  

Partial annual success with failed ranking: allow annual finalize only if product accepts rank-null — **DECISION REQUIRED**.

---

## 26. Migration Strategy (future — do not execute)

### Recommended dependency order

```text
3C.0  Human decisions + ADR acceptance
3C.1  Grading/calculation policy catalogs (immutable versions)
3C.2  Term result schema + RLS + calculate/finalize commands
3C.3  Annual result schema + calculate/finalize
3C.4  GPA persistence (if not fully embedded in annual)
3C.5  Ranking snapshots + authz
3C.6  Transcript issuance (artifact) — may remain packaging “3D” for gates
3C.7  Rebuild/replay tooling + stale detection
3C.8  Performance hardening (indexes/partition evidence)
3C.9  Production gate
```

**Note:** Phase 3 Academic Core Audit split transcripts as **3D**. This audit includes transcript **design** in 3C; implementation packaging may still use a separate human-approved **3D gate** for issuance/files. Either packaging is fine if dependencies respected (3C.6 after official results exist).

### Blueprint migration impact (proposed future DDL themes)

Enrich/replace sketch columns: add `school_id`, versioning, status, policy/calc pins, supersession; consider splitting header/lines; ranking snapshot tables; **no DEFAULT partitions**; FORCE RLS.

Object count: new tables may require blueprint count update under database-change skill **at implementation time**.

---

## 27. Rollback Strategy (describe only)

| Change | Rollback approach |
|--------|-------------------|
| Empty new tables | `down()` drop if no prod data |
| Backfilled projections | Rebuild or truncate projections; grades untouched |
| Dual-write period | Feature flag Results reads; write disable; drop flag |
| After issuance started | **No destructive rollback** of issued transcripts; forward-fix revoke/supersede |
| Policy version publish | New versions only; never edit published version in place |

---

## 28. Testing Strategy (future)

| Layer | Coverage |
|-------|----------|
| Unit | Weights, pass/fail, GPA rounding, ties, status transitions, policy pin |
| Application | Calculate/finalize/idempotency/authz/school isolation |
| PostgreSQL | RLS/FORCE/FK/unique current/version; no hard delete |
| Integration | Grade correct → stale → recalc → new version; issue transcript unchanged |
| Rebuild | Deterministic fingerprint equality |
| Concurrency | Double recalc; correct during calc; duplicate outbox |
| Performance | Large section ranking; year bulk recalc |
| Security | Ranking privacy; transcript authz; cross-school |

Use `sis_test` only for PG destructive tests; never wipe `sis`.

---

## 29. Intelligence Governance

Fits existing Rule Engine → Recommendation → Human Gate:

- Tier-1 auto: pool/replica routing only (unchanged)  
- Results stale detection = recommendation / ops alert  
- Never auto-publish grading policies or auto-issue transcripts  

---

## 30. Decision Matrix

| Decision | Option A | Option B | Option C | Recommended | Reason |
|----------|----------|----------|----------|-------------|--------|
| Grade SSOT | Results tables | `student_grades` | Dual write | **B** | Phase 3B lock |
| Term storage | No store | Flat mutable | Versioned hybrid | **Hybrid E** | Freeze + rebuild |
| Annual source | Terms only | Grades only | Grades primary + terms accel | **Grades primary** | Rebuild safety |
| GPA store | Dynamic only | Persist only | Persist + pure fn | **Both** | Explain + recover |
| Ranking store | Columns only | Snapshot tables | Live SQL always | **Snapshots (+ optional denorm)** | Audit + perf |
| Transcript | Always live | Always immutable file | Hybrid | **Hybrid** | Truth + legal doc |
| Calc trigger | Sync in grade handler | Async outbox | Nightly batch only | **Async + explicit finalize** | Decouple Exams |
| Consistency | Everything strong sync | Everything eventual | Mixed | **Mixed (§22)** | ADR-008 |
| Partition results | All LIST year | None | Defer/evidence | **Defer** | Volume |
| Policy version | Settings JSON only | Immutable versions | Code constants | **Immutable versions** | History |
| Calc version | None | Integer/semver pin | Git hash only | **Pinned integer/semver** | Explainability |
| Context boundary | Many contexts | One Results | Anemic shared | **One Results** | ADR-008 |
| Blueprint ranks | Keep only embedded | Snapshots | Drop ranks | **Snapshots** | Security/audit |
| Official mutate on correct | Auto rewrite | Supersede versions | Ignore corrections | **Supersede** | Historical truth |
| Outbox rebuild | Replay processed | Grades rebuild | Event store | **Grades rebuild** | Current job marks processed |

---

## 31. Proposed Design Locks

Status legend: **PROPOSED** = requires human approval before implementation.

### 3C-DL-001 — Result SSOT
- **Decision (PROPOSED):** `exams.student_grades` is sole authoritative score SSOT; Results never accept manual authoritative score entry.  
- **Rationale:** Phase 3B/3B.1 + Academic Core audit.  
- **Rejected:** Dual ledgers; Excel/UI masters.  
- **Impact:** All calc from grades + catalogs.  
- **Reversibility:** Low once Results go live.

### 3C-DL-002 — Term Result model
- **Decision (PROPOSED):** Versioned hybrid snapshots (operational current + explicit finalize).  
- **Rejected:** Copy-grades ledger; read-time-only for official use.  
- **Impact:** Schema versioning + commands.  
- **Reversibility:** Medium before issuance.

### 3C-DL-003 — Annual Result model
- **Decision (PROPOSED):** Versioned year rollup; rebuildable from grades; term projections optional accelerator.  
- **Rejected:** Manual annual marks; terms-only rebuild dependency.  
- **Impact:** Promotion later reads annual finalize.  
- **Reversibility:** Medium.

### 3C-DL-004 — GPA model
- **Decision (PROPOSED):** Version-pinned derived metric; formula **not locked** until human decision.  
- **Rejected:** Inventing 4.0 scale in code without evidence.  
- **Impact:** Blocks 3C.4 implementation.  
- **Reversibility:** High until first official annual finalize.

### 3C-DL-005 — Ranking model
- **Decision (PROPOSED):** Separate versioned snapshots; not authoritative scores; privacy-sensitive authz.  
- **Rejected:** Live global rank queries as sole mechanism; student peer browsing without decision.  
- **Impact:** Extra tables + permissions.  
- **Reversibility:** Medium.

### 3C-DL-006 — Transcript model
- **Decision (PROPOSED):** Hybrid live view + immutable issued artifacts (hash). Auto-change of issued docs **default NO** pending human confirmation.  
- **Rejected:** Transcript = always live print.  
- **Impact:** Object storage + issuance commands.  
- **Reversibility:** Low after first legal issuance.

### 3C-DL-007 — Historical versioning
- **Decision (PROPOSED):** Supersede/version; no silent mutate of finalized official rows.  
- **Rejected:** In-place overwrite of official results.  
- **Impact:** Matches grade VOID+INSERT philosophy.  
- **Reversibility:** Low.

### 3C-DL-008 — Calculation versioning
- **Decision (PROPOSED):** Persist `calculation_version` + fingerprint on official results.  
- **Rejected:** Undocumented algorithm drift.  
- **Impact:** Small catalog + columns.  
- **Reversibility:** High early.

### 3C-DL-009 — Event/rebuild model
- **Decision (PROPOSED):** Async outbox invalidation/recalc; **full rebuild from grades + policy catalogs**; do not rely on processed outbox history.  
- **Rejected:** Event-sourcing-only rebuild; sync Results inside Exams handlers.  
- **Impact:** Workers + rebuild commands.  
- **Reversibility:** Medium.

### 3C-DL-010 — Partition strategy
- **Decision (PROPOSED):** Do not partition Results at introduction; revisit with evidence; never DEFAULT partition pattern from grades.  
- **Rejected:** Premature LIST on all Results.  
- **Impact:** Simpler migrations.  
- **Reversibility:** High.

### 3C-DL-011 — RLS strategy
- **Decision (PROPOSED):** FORCE RLS + denorm `school_id` on all school-scoped Results/transcript/ranking tables.  
- **Rejected:** Join-only RLS without school_id.  
- **Impact:** Matches 3A/3B lessons.  
- **Reversibility:** Low after data load.

### 3C-DL-012 — CQRS boundary
- **Decision (PROPOSED):** One `Results` Application context; thin controllers; no God service; Exams remains upstream via events/read ports.  
- **Rejected:** Microservice split; fat Exams handlers calling Results.  
- **Impact:** Feature contract `Results`.  
- **Reversibility:** Medium.

### 3C-DL-013 — Consistency model
- **Decision (PROPOSED):** Mixed — strong grades & official finalize; eventual operational projections; immutable issued transcripts.  
- **Rejected:** Synchronous global consistency on every grade save.  
- **Impact:** UX expectations + stale flags.  
- **Reversibility:** Medium.

### 3C-DL-014 — Policy versioning
- **Decision (PROPOSED):** Immutable published grading policy versions required before official Results/transcripts.  
- **Rejected:** Mutable JSON settings as sole historical authority.  
- **Impact:** 3C.1 prerequisite.  
- **Reversibility:** Low after publish.

### 3C-DL-015 — No hard delete
- **Decision (PROPOSED):** No hard DELETE for finalized/issued Results artifacts; void/supersede/revoke only.  
- **Rejected:** CRUD delete APIs.  
- **Impact:** Triggers/policies at implement.  
- **Reversibility:** Low.

---

## 32. Human Decisions Required

| ID | Decision | Why blocking |
|----|----------|--------------|
| HD-01 | GPA scale & formula (4.0 vs 100 vs other) | Storage + promotion |
| HD-02 | Letter-grade bands / conversion tables | Transcript display |
| HD-03 | Weight normalization when exam type weights ≠ 100 | Term totals |
| HD-04 | Which grade statuses enter official calc (Finalized only vs Entered) | Stale/official rules |
| HD-05 | Absent / exempt / withdrawn / incomplete treatment | Pass/fail & GPA |
| HD-06 | Repeated exam / repeated subject policy | Annual/GPA |
| HD-07 | Missing term: block annual finalize vs Incomplete | Annual status |
| HD-08 | Ranking scopes required at launch | Schema |
| HD-09 | Ranking tie policy | Algorithm |
| HD-10 | Who may view whose ranks (esp. students) | Security |
| HD-11 | Issued transcript immutability vs auto-update | Legal |
| HD-12 | Transcript retention / supersede workflow | Ops |
| HD-13 | Operational refresh SLO after grade correction | UX/SLA |
| HD-14 | Whether ranking failure blocks annual finalize | Failure model |
| HD-15 | Credit-hours mandatory or optional for v1 GPA | Curriculum data quality |
| HD-16 | Keep transcript implementation as Phase 3D packaging or inside 3C gate | Program mgmt |
| HD-17 | Term closed calendar semantics | Finalize eligibility |

**Do not invent these in implementation.**

---

## 33. ADR Recommendations

Existing ADRs **ADR-013…ADR-020** are already used (outbox, idempotency, governance, etc.). **Do not reuse those numbers.**

| Proposed ADR | Title | Purpose |
|--------------|-------|---------|
| **ADR-021** | Results as Derived Academic Projections | SSOT vs derived |
| **ADR-022** | Historical Result Versioning & Supersession | Official immutability |
| **ADR-023** | Grading Policy Versioning | Historical explainability |
| **ADR-024** | GPA Calculation Policy | After HD-01… |
| **ADR-025** | Ranking Policy & Privacy | Ties + visibility |
| **ADR-026** | Transcript Issuance & Immutability | Hybrid model |
| **ADR-027** | Calculation Versioning & Rebuild-from-Grades | Replay/rebuild |
| **ADR-028** | Results Consistency (Async Projections + Explicit Finalize) | SLO/consistency |

Draft ADRs only after human locks corresponding Design Locks / HDs. Audit-stage: **recommend only** (this document).

---

## 34. Risks

| Risk | Level | Mitigation |
|------|-------|------------|
| Implementing blueprint sketch as-is (no version/school_id) | HIGH | Enrich before migrate |
| Silent historical mutation | CRITICAL | DL-007 |
| GPA formula invented by engineers | HIGH | HD-01 gate |
| Ranking privacy leak | HIGH | Separate permission + RLS |
| Coupling Exams handlers to Results | MEDIUM | Outbox ACL |
| Rebuild assumed from processed outbox | HIGH | Rebuild from grades |
| Premature partitioning complexity | MEDIUM | DL-010 |
| Transcript file loss without hash/metadata | HIGH | Issuance design |
| Bulk year recalc overload | MEDIUM | Queue throttle/checkpoints |
| Policy change rewriting history | CRITICAL | DL-014 |

---

## 35. Deferred Items

- Report-card UI / dashboards  
- Notifications  
- Promotion execution (reads Results later)  
- Certificates beyond transcript  
- Directorate analytics MVs  
- Submitted grade workflow (still out of grade phase scope)  
- Credit transfer / external grades import  
- Multi-curriculum simultaneous GPA schemes beyond versioned policies  
- Real-time websocket rank updates  

---

## 36. Proposed Implementation Phases

Each future phase requires **separate human approval** before coding/DDL.

### Phase 3C.0 — Prerequisite decisions
- **Scope:** Resolve HD-01…HD-17; accept Design Locks; draft ADR-021+  
- **Dependencies:** Phase 3B.1 complete  
- **Migrations:** NONE  
- **Security/tests:** Decision record only  
- **Rollback:** N/A  
- **Production criteria:** Signed decision log  
- **Human approval:** YES  

### Phase 3C.1 — Policy & calculation catalogs
- **Scope:** `grading_policy(_versions)`, calculation version registry  
- **Dependencies:** 3C.0  
- **Migrations:** Catalog tables + RLS as applicable  
- **Security:** `results.manage_policy`  
- **Tests:** Immutability of published versions  
- **Rollback:** Drop if unused  
- **Production criteria:** At least one published policy version per active school/year  
- **Human approval:** YES  

### Phase 3C.2 — Term results
- **Scope:** Schema (enriched), Calculate/Finalize/Recalculate, Outbox consumer invalidation, RLS  
- **Dependencies:** 3C.1; grades SSOT  
- **Migrations:** term result tables/indexes/RLS  
- **Security:** `results.view|calculate|finalize`  
- **Tests:** unit/app/PG/integration correction→recalc  
- **Rollback:** Feature flag reads; drop empty tables  
- **Production criteria:** Sample school year recalculated; stale detection on  
- **Human approval:** YES  

### Phase 3C.3 — Annual results
- **Scope:** Annual versions + finalize; rebuild-from-grades path  
- **Dependencies:** 3C.2 (or parallel after 3C.1 if grades-only calc)  
- **Migrations:** annual tables  
- **Security:** same + tighter finalize  
- **Tests:** incomplete year behaviors per HD-07  
- **Rollback:** flag  
- **Production criteria:** Annual finalize dry-run  
- **Human approval:** YES  

### Phase 3C.4 — GPA
- **Scope:** Implement locked formula; persist on annual/term as decided  
- **Dependencies:** HD-01…; 3C.3  
- **Migrations:** columns/tables as needed  
- **Security:** view vs recalculate  
- **Tests:** rounding fixtures golden vectors  
- **Rollback:** hide GPA fields  
- **Production criteria:** Golden vectors signed by academics  
- **Human approval:** YES  

### Phase 3C.5 — Ranking
- **Scope:** Snapshots + authz matrix  
- **Dependencies:** HD-08…10; annual/term totals  
- **Migrations:** ranking tables  
- **Security:** `results.view_ranking`  
- **Tests:** ties; privacy negatives  
- **Rollback:** disable endpoints  
- **Production criteria:** Privacy review signed  
- **Human approval:** YES  

### Phase 3C.6 — Transcript issuance
- **Scope:** Issue/revoke/supersede; file hash; idempotency  
- **Dependencies:** Official results; HD-11…12; object storage  
- **Migrations:** transcripts enrichment  
- **Security:** issue/revoke permissions  
- **Tests:** issued doc unchanged after grade correct  
- **Rollback:** forward-fix only after issuance  
- **Production criteria:** Hash verify on download; legal review  
- **Human approval:** YES (may be labeled Phase 3D)  

### Phase 3C.7 — Rebuild & ops
- **Scope:** Rebuild jobs, checkpoints, DLQ, stale dashboard  
- **Dependencies:** 3C.2–3C.6  
- **Migrations:** ops tables if any  
- **Tests:** deterministic rebuild  
- **Human approval:** YES  

### Phase 3C.8 — Performance hardening
- **Scope:** Measured indexes/partition/replica use  
- **Dependencies:** Evidence from 3C.2–3C.7 load  
- **Migrations:** only with before/after evidence  
- **Human approval:** YES if schema  

### Phase 3C.9 — Production gate
- **Scope:** Gate report; security:validate; architecture:feature-check Results; PG RLS; smoke  
- **Production criteria:** Partitions for grades exist; policies published; outbox consumers healthy; no Phase 3C write paths without authz  
- **Human approval:** YES  

---

## 37. Final Audit Gate

```text
PASS WITH CONDITIONS
```

### Why not PASS

Too many unresolved business rules (GPA, ranking privacy/ties, transcript legal immutability, official calc eligibility) to authorize schema/application implementation safely.

### Why not BLOCKED

No foundational incompatibility with Phase 3B/3B.1 was found. Academic terms exist. Grade SSOT and correction model support a derived Results architecture. Existing Outbox/CQRS/RLS governance can host Results without a new framework.

### Conditions to lift before implementation

1. Human decisions HD-01…HD-17 (minimum: HD-01, HD-04, HD-09, HD-10, HD-11)  
2. Acceptance of Design Locks 3C-DL-001…015 (or documented amendments)  
3. ADR-021+ drafts accepted for locked topics  
4. Explicit human approval of the first implementation phase (3C.1 or 3C.2)  

---

## Appendix A — Anti-Corruption / Read Ports (future)

Results should consume via Application ports, not random Eloquent across contexts:

| Port | Purpose |
|------|---------|
| `GradeReadForResultsPort` | Current/finalized grades by enrollment/term |
| `ExamGraphReadPort` | Exam/session/type weights |
| `EnrollmentPlacementReadPort` | Class/section for ranking cohorts |
| `AcademicCalendarReadPort` | Terms/year |
| `SubjectPolicyReadPort` | Pass/credits |

Exams must not reference Results tables.

---

## Appendix B — Bulk Recalculation (future design)

Scopes: student / enrollment / class / school / academic year / system.  

Mechanics: Laravel Queue, chunked enrollments, checkpoint table, cancel flag, throttle, idempotent version writes, advisory locks per enrollment, progress metrics — **never** inline HTTP.

---

## Appendix C — Engine portability

| Dependency | Class |
|------------|-------|
| PostgreSQL RLS/FORCE | **Required** for tenant safety |
| LIST partitions on grades | **Required** (existing) |
| Window functions for ad-hoc rank | Optional optimization (prefer snapshots) |
| Materialized views | Optional analytics |
| `FOR UPDATE` | Required pattern for concurrent finalize (as grades) |

Do not weaken PG safety for theoretical portability (ADR-001).

---

## Appendix D — Explicit non-claims

```text
Phase 3C IMPLEMENTATION STATUS: NOT STARTED
Database changes: NONE
Migrations: NONE
API changes: NONE
UI changes: NONE
```

```text
Phase 3C implementation requires separate explicit human approval.
```

**STOP.** Await HUMAN REVIEW of this audit before any Phase 3C implementation.
