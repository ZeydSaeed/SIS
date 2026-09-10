# SIS DATABASE — PHASE 3C.0  
# HUMAN DECISION & DESIGN LOCK GATE

**Document type:** GOVERNANCE / DECISION REGISTER / ADR DRAFTS ONLY  
**Date:** 2026-09-10  
**Predecessor:** `docs/database/SIS-DATABASE-PHASE-3C-RESULTS-ARCHITECTURE-AUDIT.md`  
**Baseline gates:** Phase 3A · Phase 3B · Phase 3B.1  

```text
IMPLEMENTATION AUTHORIZATION: NOT GRANTED
NO DDL · NO MIGRATIONS · NO CODE · NO DATABASE MODIFICATION
```

---

## 1. Executive Summary

Phase 3C.0 freezes architecture into an explicit **Design Lock Register (DL-001…DL-016)** and a **Human Decision Register (HD-01…HD-18)**, backed by read-only repository evidence and **DRAFT** ADRs **021–028**.

**Findings:**

- Phase 3B / 3B.1 remain compatible with a derived Results context; **no SSOT conflict** requiring redesign of `exams.student_grades`.
- Many **product/academic policies** (GPA formula, letter bands, ranking ties/privacy, official eligibility, transcript legal immutability) have **no authoritative repository rule** → must stay **HUMAN DECISION REQUIRED**.
- Transcript **architecture** belongs in Phase 3C design; **issuance implementation** remains recommended as **Phase 3D** (consistent with Phase 3A / Academic Core staging).
- Deterministic rebuild is elevated to **DL-016** as an architectural invariant.

### Final gate (this phase)

```text
PHASE 3C.0 GATE: PASS WITH CONDITIONS
```

```text
NEXT PHASE: PHASE 3C.1 — POLICY & CALCULATION CATALOGS
```

Phase 3C.1 must **not** start until the human reviews this report and **explicitly approves** proceeding.

---

## 2. Phase Scope

| In scope | Out of scope |
|----------|--------------|
| Read-only repository evidence audit | Migrations / schema / RLS / partitions |
| Design Lock Register DL-001…DL-016 | Commands / handlers / APIs / UI |
| Human Decision Register HD-01…HD-18 | GPA / ranking / transcript calculators |
| ADR-021…ADR-028 **DRAFTS** | Queue workers / outbox consumers |
| Compatibility vs 3B/3B.1 | Any database write / wipe / migrate |
| Transcript 3C/3D boundary recommendation | Phase 3C.1+ implementation |

---

## 3. Implementation Freeze Confirmation

This phase performed **documentation only**.

| Action | Status |
|--------|--------|
| `migrate` / `migrate:fresh` / `db:wipe` | **NOT RUN** |
| CREATE/ALTER/DROP/TRUNCATE | **NOT EXECUTED** |
| Application / API / UI code changes | **NONE** |
| Live `sis` modified | **NO** |
| `sis_test` schema modified | **NO** |
| Destructive tests | **NOT RUN** |

Allowed operations used: repository search/read, git metadata, architecture doc inspection.

---

## 4. Repository Evidence Reviewed

### Primary artifacts

| Source | Role |
|--------|------|
| `docs/database/SIS-DATABASE-PHASE-3C-RESULTS-ARCHITECTURE-AUDIT.md` | Phase 3C audit/design |
| `docs/database/SIS-DATABASE-PHASE-3B*.md` / `…3B.1…` | Grade SSOT + app gate |
| `docs/database/SIS-DATABASE-PHASE-3A-GATE.md` | Exam foundation; transcripts → 3D |
| `docs/database/SIS-DATABASE-PHASE-3-ACADEMIC-CORE-AUDIT.md` | Staged 3A→3B→3C→3D |
| `.cursor/architecture/database-blueprint.md` | Sketch tables (not implemented Results DDL) |
| `.cursor/architecture/data-quality-rules.md` | Ambiguous GPA scale note |
| `.cursor/architecture/security-audit-resilience.md` | Transcript/certificate SHA-256 integrity |
| `.cursor/architecture/adr/ADR-001…020` | Occupied ADR numbers; CQRS-lite, outbox, partition, RLS |
| `database/migrations/*academic*`, `*curriculum*`, `*phase3a*`, `*phase3b*` | LIVE schema evidence |
| `app/Domain/Exams/**` | GradeStatus, absence, enrollment seat status, events |
| `app/Domain/Enrollment/ValueObjects/EnrollmentStatus.php` | Active/Cancelled only |
| `app/Infrastructure/Jobs/ProcessOutboxJob.php` | Outbox consumer pattern; no Results bridges |
| `docs/sis/exams/SECURITY-CONTRACT.md` | Grade permissions / finalize / no hard delete |
| `config/security.php` | Current permissions (no `results.*` yet) |

### Not found (authoritative product policy)

No authoritative tables/enums/services defining: GPA formula, letter bands, ranking tie policy, ranking privacy matrix, term closed lifecycle for Results, exempt/incomplete academic result semantics, legal transcript retention, national curriculum regulations.

---

## 5. Phase 3B / 3B.1 Compatibility

| Check | Result | Evidence |
|-------|--------|----------|
| Grade SSOT remains `exams.student_grades` | **PASS** | 3B/3B.1 gates; 3C audit DL-001 |
| Results must not become second grade ledger | **PASS (design)** | 3C audit; this gate DL-001 |
| Correction = VOID + INSERT | **PASS** | 3B.1 gate; `CorrectStudentGradeHandler` |
| School isolation stack | **PASS (extend)** | Grades FORCE RLS + Policy + SchoolContext |
| CQRS / no God service | **PASS (extend)** | Enrollment + Exams pattern |
| Async outbox for side effects | **PASS (extend)** | ADR-013; grade events staged |
| Rebuild not solely from processed outbox | **PASS (design gap closed by DL-009/016)** | `ProcessOutboxJob` marks processed without Results work |
| Historical official Results no silent mutate | **PASS (design)** | DL-007 |
| Intelligence cannot invent academic policy | **PASS (governance)** | Intelligence human-gate pattern |

**No Phase 3B/3B.1 redesign required** for Results to proceed after human approvals.

### Known documentation conflict (report only; do not silent-resolve)

| Topic | Source A | Source B |
|-------|----------|----------|
| Unique grade natural key | `data-quality-rules.md`: UNIQUE `(exam_session_id, student_id)` | Phase 3B: partial UNIQUE `(exam_enrollment_id, academic_year_id) WHERE is_current` |

**Classification:** Phase 3B implementation is **authoritative for LIVE grades**. Data-quality doc is **stale / non-authoritative** relative to 3B unless later updated. Results design must use **exam enrollment / current-grade** semantics.

---

## 6. Results Architecture Baseline

```text
exams.student_grades  (SSOT)
        │
        ▼
Transactional Outbox (grade domain events)
        │
        ▼
Results invalidation / recalculation (FUTURE — not implemented)
        │
        ├─ Term Results (versioned hybrid)
        ├─ Annual Results (versioned year rollup)
        ├─ GPA (version-pinned derived)
        ├─ Ranking snapshots (non-authoritative comparative)
        └─ Transcript: live projection (3C design) + issued artifact (3D implement)
```

**Bounded context:** one `Results` context with internal modules (DL-012).  
**Consistency:** mixed (DL-013).  
**Partition:** not initially (DL-010).

---

## 7. Design Lock Register (DL-001…DL-016)

All locks below are **PROPOSED pending human approval** unless marked otherwise.

| ID | Decision | Status | Rationale (short) | Alternatives rejected | Impact | Reversibility |
|----|----------|--------|-------------------|----------------------|--------|---------------|
| **DL-001** | Results SSOT = `exams.student_grades` only | PROPOSED | 3B/3B.1 established | Dual ledger; editable Results scores | Blocks second grade store | Low after go-live |
| **DL-002** | Term results = versioned hybrid derived storage | PROPOSED | Official freeze + operational current | Copy-grades; compute-only official | Schema + commands | Medium pre-issuance |
| **DL-003** | Annual = versioned year rollup; grades primary rebuild source | PROPOSED | Rebuild safety | Manual annual marks; terms-only dependency | Annual schema | Medium |
| **DL-004** | GPA version-pinned & persisted; **formula not assumed** | PROPOSED | Explainability without inventing math | Dynamic-only; invent 4.0 now | Needs HD-01 | High until first finalize |
| **DL-005** | Ranking = separate versioned snapshots; not academic truth | PROPOSED | Audit + privacy + perf | Live-only ranks; ranks as SSOT | Ranking tables + authz | Medium |
| **DL-006** | Transcript hybrid: live projection + immutable issued artifact | PROPOSED | Truth + document reproducibility | Always-live print as legal doc | Pins + hash | Low after first issue |
| **DL-007** | Historical official Results: version/supersede; no silent mutate | PROPOSED | Matches VOID+INSERT philosophy | In-place overwrite | Version columns | Low |
| **DL-008** | Official calc stores `calculation_version`, `calculated_at`, source fingerprint | PROPOSED | Explainability | Undocumented drift | Columns/catalog | High early |
| **DL-009** | Grade→Results async via outbox; rebuild from grades + immutable policies | PROPOSED | ADR-013; processed outbox insufficient | Sync Results in Exams handlers; outbox-only rebuild | Workers + rebuild jobs | Medium |
| **DL-010** | Results tables not partitioned initially; evidence later | PROPOSED | Volume ≪ attendance | Premature LIST everywhere | Simpler 3C.1–3C.5 DDL | High |
| **DL-011** | School stack: Policy→SchoolContext→Handler→Composite FK→RLS→FORCE RLS | PROPOSED | 3A/3B lessons | Join-only RLS | Denorm `school_id` | Low after load |
| **DL-012** | One Results bounded context; no microservices | PROPOSED | ADR-008 | Split GPA/Ranking deployables | Feature contract `Results` | Medium |
| **DL-013** | Mixed consistency (grades strong; ops eventual; official strong+immutable; ranking eventual; issued immutable; live transcript eventual) | PROPOSED | ADR-008 + academic truth | Sync global consistency | SLO + stale flags | Medium |
| **DL-014** | Published grading policy versions immutable | PROPOSED | Historical explainability | Mutable JSON-only authority | 3C.1 catalogs | Low after publish |
| **DL-015** | No hard-delete of finalized Results / issued transcript artifacts | PROPOSED | Constitution / grade pattern | CRUD DELETE | Supersede/revoke/void | Low |
| **DL-016** | **Deterministic rebuild** invariant (see §12) | PROPOSED | Auditability + corruption recovery | Non-deterministic calc | Fingerprints + tests | Medium |

---

## 8. Human Decision Register (HD-01…HD-18)

### Evidence classification key

1. **AUTHORITATIVE REPOSITORY EVIDENCE**  
2. **EXISTING ARCHITECTURAL DECISION**  
3. **INFERRED / NON-AUTHORITATIVE**  
4. **MISSING — HUMAN DECISION REQUIRED**

---

### HD-01 — GPA scale and formula

| Item | Content |
|------|---------|
| **Evidence** | `data-quality-rules.md`: “GPA \| 0–4 or 0–100” (**ambiguous**). Blueprint `annual_results.gpa NUMERIC(4,2)` (sketch). `subjects.credit_hours` **nullable**. `promotion.rules.min_gpa` blueprint-only. **No formula service/enum.** |
| **Classification** | **4 — HUMAN DECISION REQUIRED** (data-quality is non-decisive) |
| **Options** | Percentage average · Weighted subject average · Credit-hour GPA · 4.0 scale · 100-point scale · Institution-defined · Other |
| **Recommendation** | **None locked.** Do not invent national formula. |
| **Human approval** | **MANDATORY** before ADR-024 acceptance / Phase 3C.4 |

---

### HD-02 — Letter-grade bands and conversion

| Item | Content |
|------|---------|
| **Evidence** | Blueprint `term_results.grade_letter VARCHAR(5)`. Phase 3B audit *recommended optional* frozen letter at finalize — **not implemented** on `student_grades`. No band table/config. |
| **Classification** | **4 — HUMAN DECISION REQUIRED** |
| **Options** | No letters in v1 · School policy bands · National bands · Derive only at transcript |
| **Recommendation** | Defer bands until policy catalog (3C.1) after human scale choice. |
| **Human approval** | **MANDATORY** if letters appear on official Results/transcripts |

---

### HD-03 — Exam-weight normalization

| Item | Content |
|------|---------|
| **Evidence** | `exam_types.weight_percentage` CHECK `BETWEEN 0 AND 100` (per type). **No rule** for sum of weights in a term/subject. |
| **Classification** | **4 — HUMAN DECISION REQUIRED** (field exists; policy missing) |
| **Options** | Reject if sum≠100 · Normalize proportionally · Allow configured total · Institution-defined |
| **Recommendation** | None without academic policy owner. |
| **Human approval** | **MANDATORY** before official term aggregation |

---

### HD-04 — Grade-status eligibility (official calc)

| Item | Content |
|------|---------|
| **Evidence** | `GradeStatus`: Draft=1, Entered=2, Submitted=3, Finalized=4, Voided=5. 3B.1: Submitted workflow **out of scope**; finalize from Draft/Entered. Voided not current. **No Results rule** selecting which statuses enter official aggregates. |
| **Classification** | Status enum = **1**; eligibility policy = **4** |
| **Options** | Finalized-only · Entered+Finalized · Include Submitted · Exclude Draft/Voided (always) |
| **Recommendation (architectural hint only)** | Official finalize **likely** Finalized-only — **not locked**. |
| **Human approval** | **MANDATORY** — distinct from HD-18 |

---

### HD-05 — Absent / Exempt / Withdrawn / Incomplete (calculation)

| Item | Content |
|------|---------|
| **Evidence** | Grade `is_absent` ⇒ score NULL (**authoritative grade semantics**). Exam seat `Withdrawn`/`Absent`. Enrollment Active/Cancelled. **No Exempt/Incomplete result domain.** |
| **Classification** | Absence mechanics = **1**; result calc treatment = **4**; Exempt/Incomplete = **4** |
| **Options** | Exclude from average · Count as zero · Force fail · Incomplete annual · Institution-defined |
| **Recommendation** | Do not invent. |
| **Human approval** | **MANDATORY** |

---

### HD-06 — Repeated exam / repeated subject

| Item | Content |
|------|---------|
| **Evidence** | Phase 3B audit: retake via **new** exam_session/exam_enrollment → new grade row (**mechanism**). No “best/last/first wins” policy. |
| **Classification** | Mechanism = **2/3**; selection policy = **4** |
| **Options** | Latest · Highest · First · All contribute · Institution-specific |
| **Recommendation** | None. |
| **Human approval** | **MANDATORY** |

---

### HD-07 — Missing term / incomplete year / transfer / withdraw

| Item | Content |
|------|---------|
| **Evidence** | Terms exist; no Results incomplete-year rules. Enrollment cancel exists; transfer module not Results-scoped. |
| **Classification** | **4** |
| **Options** | Block annual finalize · Allow Incomplete status · Partial annual · Institution-specific |
| **Recommendation** | None. |
| **Human approval** | **MANDATORY** |

---

### HD-08 — Ranking scopes

| Item | Content |
|------|---------|
| **Evidence** | Blueprint: `rank_in_section` (term), `rank_in_section`/`rank_in_class` (annual). Enrollment has class/section. No program/school-board policy. |
| **Classification** | Sketch columns = **3**; launch scopes = **4** |
| **Options** | Section · Class · Program · School · Cohort · Academic year · Subset |
| **Recommendation** | Start from blueprint section/class as **candidates** only. |
| **Human approval** | **MANDATORY** for launch set |

---

### HD-09 — Ranking tie policy

| Item | Content |
|------|---------|
| **Evidence** | None in domain/code. |
| **Classification** | **4** |
| **Options** | Competition (1224) · Dense (1223) · Ordinal · No rank on ties · Institution-defined |
| **Recommendation** | None. |
| **Human approval** | **MANDATORY** before ADR-025 acceptance |

---

### HD-10 — Ranking privacy

| Item | Content |
|------|---------|
| **Evidence** | No ranking permissions. Grades have `grades.view`. |
| **Classification** | **4** |
| **Options** | Own rank only · Class/section boards for teachers · Full school board for registrar · Students see peers — yes/no |
| **Recommendation (architecture only)** | Introduce separate permission `results.view_ranking`; default **deny peer boards for students** until approved — **not a locked product rule**. |
| **Human approval** | **MANDATORY** |

---

### HD-11 — Issued transcript after later grade correction

| Item | Content |
|------|---------|
| **Evidence** | SHA-256 integrity pattern for certificates/transcripts (`security-audit-resilience.md`). Blueprint transcript `file_hash`. **No legal rule** auto-update vs immutable. 3C audit proposes hybrid immutability. |
| **Classification** | Integrity mechanism = **2**; legal behavior = **4** |
| **Options** | **A** Remain immutable; issue superseding transcript · **B** Automatically change issued transcript |
| **Recommendation (architecture preference, not approved)** | Prefer **A** to align with DL-006/007/015 and hash integrity — **HUMAN MUST CONFIRM**. |
| **Human approval** | **MANDATORY / CRITICAL** |

---

### HD-12 — Transcript retention and supersession

| Item | Content |
|------|---------|
| **Evidence** | Blueprint: `transcript_number` UNIQUE; storage_key; file_hash. No retention period, revoke/supersede workflow in code. |
| **Classification** | **4** |
| **Options** | Retention years · Revoke semantics · Supersede numbering · Artifact retention in object storage |
| **Recommendation** | Define in Phase 3D after HD-11; architecture lifecycle only in 3C. |
| **Human approval** | **MANDATORY** (legal/ops) |

---

### HD-13 — Operational Results refresh SLO

| Item | Content |
|------|---------|
| **Evidence** | ADR-008: summary eventual. ADR-013: outbox job ~minute cadence. 3C audit example 1–5 minutes. |
| **Classification** | Infra cadence = **2**; product SLO = **4** |
| **Options** | ≤1 min · 1–5 min · ≤15 min · Best-effort · Institution SLA |
| **Recommendation** | Propose **1–5 minutes typical** as **candidate for approval**, not locked. |
| **Human approval** | **MANDATORY** to publish SLO |

---

### HD-14 — Ranking failure vs Annual Finalization

| Item | Content |
|------|---------|
| **Evidence** | None. |
| **Classification** | **4** |
| **Options** | Ranking non-blocking · Ranking required · Institution-specific |
| **Recommendation** | None. |
| **Human approval** | **MANDATORY** |

---

### HD-15 — Credit-hours for GPA v1

| Item | Content |
|------|---------|
| **Evidence** | `curriculum.subjects.credit_hours` **nullable**; vocational also nullable credits. |
| **Classification** | Schema = **1**; GPA requirement = **4** |
| **Options** | Credits mandatory · Credits optional · Percentage GPA without credits · Institution-defined |
| **Recommendation** | If HD-01 chooses non-credit GPA, credits optional for v1 — still needs human. |
| **Human approval** | **MANDATORY** with HD-01 |

---

### HD-16 — Transcript packaging boundary (3C vs 3D)

| Item | Content |
|------|---------|
| **Evidence** | Phase 3A gate: transcripts → Phase 3D. Academic Core audit: 3C derived results; 3D transcript artifacts. This prompt: architecture in 3C; issuance in 3D. |
| **Classification** | **2 — EXISTING ARCHITECTURAL DECISION (staging)** + confirm |
| **Options** | Keep 3D issuance · Fold issuance into 3C · Hybrid packaging |
| **Recommendation** | **Approve:** 3C = architecture/contracts; **3D = issuance implementation**. |
| **Human approval** | **MANDATORY confirmation** |

---

### HD-17 — Term closed-calendar semantics

| Item | Content |
|------|---------|
| **Evidence** | `academic.terms.status` SMALLINT; dates; `term_order`. **No** closed/reopen/results-lock domain. |
| **Classification** | **4** |
| **Options** | Open/closed/finalized/reopened semantics · Reopen supersedes Results · Block calc when open · Institution-defined |
| **Recommendation** | Do not invent; may need future academic calendar enrichment (document only). |
| **Human approval** | **MANDATORY** before finalize eligibility automation |

---

### HD-18 — Result calculation eligibility boundary (dataset completeness)

| Item | Content |
|------|---------|
| **Evidence** | Partial building blocks: active exam seat; active enrollment; grade exists; grade status (HD-04); exam graph completeness — **no composed official eligibility policy**. |
| **Classification** | Components = **1/2**; composed boundary = **4** |
| **Must distinguish from HD-04** | HD-04 = which **grade statuses** count. HD-18 = whether the **student dataset** is complete enough to calculate/finalize officially. |
| **Factors to decide** | Valid exam enrollment · Valid academic enrollment · Required grade exists · Required grade finalized · All required assessments exist · Required subjects complete · Policy requirements · Withdrawal/incomplete resolved · Term/year eligible |
| **Recommendation** | None locked. |
| **Human approval** | **MANDATORY** — separate from HD-04 |

---

## 9. Evidence Classification Summary

| Area | Best classification |
|------|---------------------|
| Grade SSOT / VOID+INSERT / CQRS / Outbox / RLS pattern | **1 / 2** |
| Academic year → term → exam → session → seat → grade | **1** |
| `weight_percentage`, `max_grade`, `pass_grade`, nullable `credit_hours` | **1** (fields, not policies) |
| GPA formula / letter bands / ties / privacy / retention | **4** |
| Transcript SHA-256 integrity pattern | **2** |
| Transcript legal immutability (HD-11) | **4** |
| 3C vs 3D packaging | **2** (confirm HD-16) |
| Term closed lifecycle | **4** |
| Eligibility boundary HD-18 | **4** |

---

## 10. Conflicting Evidence

| Conflict | Resolution for Results design |
|----------|-------------------------------|
| Data-quality UNIQUE `(exam_session_id, student_id)` vs LIVE 3B UNIQUE current `(exam_enrollment_id, academic_year_id)` | **Prefer LIVE 3B**; update data-quality later (not in 3C.0) |
| Blueprint embeds ranks on result rows vs DL-005 snapshot tables | Prefer **snapshots** (+ optional denorm copy); blueprint enrichment at implement |
| GPA “0–4 or 0–100” | Treat as **undecided**, not a formula |
| Optional letter freeze in 3B audit vs not implemented | Letters remain **policy decision**, not LIVE feature |

---

## 11. Proposed Decision Matrix

| ID | Decision | Proposed Direction | Evidence | Confidence | Human Approval |
|----|----------|-------------------|----------|------------|----------------|
| DL-001 | Grade SSOT for Results | `exams.student_grades` only | 3B/3B.1 | High | Approve lock |
| DL-002 | Term storage | Versioned hybrid | 3C audit | High | Approve lock |
| DL-003 | Annual storage | Versioned; rebuild from grades | 3C audit | High | Approve lock |
| DL-004 | GPA persistence | Persist + pin; formula open | Schema nullable credits; DQ ambiguous | Med | Approve lock **without** formula |
| DL-005 | Ranking | Versioned snapshots | Blueprint sketch only | Med-High | Approve lock |
| DL-006 | Transcript model | Hybrid live + immutable issue | Security hash + 3C audit | High arch | Approve lock; HD-11 for legal |
| DL-007 | Historical official | Version/supersede | Matches grades | High | Approve lock |
| DL-008 | Calc metadata | version + at + fingerprint | 3C audit | High | Approve lock |
| DL-009 | Propagation/rebuild | Async outbox; rebuild from grades | ProcessOutboxJob reality | High | Approve lock |
| DL-010 | Partition Results | Not initially | Capacity vs attendance | Med | Approve lock |
| DL-011 | Tenant isolation | Full stack + school_id | 3A/3B | High | Approve lock |
| DL-012 | Context boundary | One Results context | ADR-008 | High | Approve lock |
| DL-013 | Consistency | Mixed | ADR-008/013 | High | Approve lock |
| DL-014 | Policy versions | Immutable published | No catalog yet | High arch | Approve lock |
| DL-015 | No hard delete | Supersede/revoke/void | Constitution/grades | High | Approve lock |
| DL-016 | Deterministic rebuild | Equivalent in → equivalent out | 3C.0 requirement | High | Approve lock |
| HD-01 | GPA formula | — | Missing | — | **REQUIRED** |
| HD-02 | Letter bands | — | Missing | — | **REQUIRED** if used |
| HD-03 | Weight sum≠100 | — | Field only | — | **REQUIRED** |
| HD-04 | Status eligibility | — | Enum only | — | **REQUIRED** |
| HD-05 | Absent/exempt/etc. | — | Partial | — | **REQUIRED** |
| HD-06 | Repeat policy | — | Mechanism only | — | **REQUIRED** |
| HD-07 | Missing term | — | Missing | — | **REQUIRED** |
| HD-08 | Rank scopes | Candidates: section/class | Blueprint | Low | **REQUIRED** |
| HD-09 | Tie policy | — | Missing | — | **REQUIRED** |
| HD-10 | Rank privacy | Suggest `results.view_ranking` | Missing | Low | **REQUIRED** |
| HD-11 | Issued transcript on correct | Prefer A (immutable) | Hash pattern | Med arch | **REQUIRED** |
| HD-12 | Retention/supersede | — | Missing | — | **REQUIRED** |
| HD-13 | Refresh SLO | Candidate 1–5 min | Outbox cadence | Low | **REQUIRED** |
| HD-14 | Rank fail vs annual finalize | — | Missing | — | **REQUIRED** |
| HD-15 | Credits for GPA v1 | — | Nullable credits | — | **REQUIRED** w/ HD-01 |
| HD-16 | 3C/3D boundary | Arch 3C / issue 3D | Phase 3A/Core | High | **CONFIRM** |
| HD-17 | Term closed semantics | — | status only | — | **REQUIRED** |
| HD-18 | Dataset eligibility boundary | — | Missing composed rule | — | **REQUIRED** |

---

## 12. Deterministic Rebuild Invariant (DL-016)

### Invariant statement

> The same authoritative input state **MUST** produce an **equivalent** derived Results output when rebuilt using the same:
>
> - authoritative grade set (identity + scores + absence + currency/status as defined by HD-04/HD-18),  
> - academic structure (year, term, exam graph, enrollment placement as applicable),  
> - grading policy version,  
> - calculation version,  
> - ranking policy (where applicable),  
> - eligibility boundary (HD-18).

### Required capabilities (future implementation — not now)

- Reproducible **source fingerprint**  
- Deterministic calculation  
- Deterministic rebuild  
- Auditability  
- Comparison between previous and rebuilt versions (fingerprint / semantic equality)

### Non-goals for 3C.0

Do not specify algorithms, SQL, or code structure beyond this invariant.

---

## 13. Transcript 3C / 3D Boundary

### Phase 3C (design / contracts only until later approved phases)

Define:

- transcript architecture  
- live projection vs issued artifact  
- immutability rules (pending HD-11)  
- source-result version pins  
- calculation version / policy version  
- fingerprint / hash  
- security permissions (design)  
- supersession / revocation lifecycle (design)  

### Phase 3D (implementation — separate human approval)

Implement:

- issuance  
- artifact generation & storage  
- numbering  
- hash verification  
- revoke / supersede / retrieval  
- audit & operational hardening  

**Recommendation for human confirmation (HD-16):** keep this split.

---

## 14. ADR Draft Register (ADR-021…ADR-028)

> **Status for all:** `DRAFT / HUMAN APPROVAL REQUIRED`  
> **Not ACCEPTED.** Numbers 013–020 already occupied.

---

### ADR-021 — Results as Derived Academic Projections

| Field | Content |
|-------|---------|
| **Status** | DRAFT / HUMAN APPROVAL REQUIRED |
| **Context** | Need term/annual/GPA/ranking/transcript without second grade ledger |
| **Problem** | Risk of Results becoming editable SSOT |
| **Decision** | Results are derived projections/snapshots; SSOT = `exams.student_grades` (DL-001) |
| **Alternatives** | Dual write · Manual Results marks · Event-sourced grades |
| **Consequences** | Calc engines + rebuild; no Results grade entry API |
| **Security** | Results authz ≠ grade entry authz |
| **Integrity** | No competing scores |
| **Performance** | Projections for read paths |
| **Historical** | Versioned derived history ≠ grade history |
| **Rollback** | Drop unused projections; grades untouched |
| **Relation to 3B/3B.1** | Consumes grades; does not alter correction model |
| **Open HDs** | HD-04, HD-18 |
| **Approval state** | Pending |

---

### ADR-022 — Historical Result Versioning and Supersession

| Field | Content |
|-------|---------|
| **Status** | DRAFT / HUMAN APPROVAL REQUIRED |
| **Context** | Grade corrections change current truth over decades |
| **Problem** | Silent mutation of official results |
| **Decision** | Official Results versioned; supersede/void; no silent mutate (DL-007/015) |
| **Alternatives** | In-place overwrite · Append-only without current pointer |
| **Consequences** | `version`/`is_current`/`supersedes_id` |
| **Security** | Elevated finalize/supersede permissions |
| **Integrity** | Auditable chain |
| **Performance** | More rows; indexes on current |
| **Historical** | Prior official versions remain readable |
| **Rollback** | Forward-fix after official publish |
| **Relation to 3B/3B.1** | Mirrors VOID+INSERT philosophy |
| **Open HDs** | HD-11, HD-17 |
| **Approval state** | Pending |

---

### ADR-023 — Grading Policy Versioning

| Field | Content |
|-------|---------|
| **Status** | DRAFT / HUMAN APPROVAL REQUIRED |
| **Context** | Letter/weight/GPA conversion may change over time |
| **Problem** | Mutable settings rewrite history |
| **Decision** | Immutable published policy versions; pin on official Results (DL-014) |
| **Alternatives** | Live settings only · Code constants only |
| **Consequences** | Phase 3C.1 catalogs |
| **Security** | `results.manage_policy` elevated |
| **Integrity** | Historical explainability |
| **Performance** | Small catalog tables |
| **Historical** | Old results keep old policy id |
| **Rollback** | New versions only; never edit published |
| **Relation to 3B/3B.1** | Complements max_score snapshots |
| **Open HDs** | HD-01, HD-02, HD-03 |
| **Approval state** | Pending |

---

### ADR-024 — GPA Calculation Policy

| Field | Content |
|-------|---------|
| **Status** | DRAFT / HUMAN APPROVAL REQUIRED |
| **Context** | Blueprint GPA field; promotion min_gpa sketch; DQ ambiguous |
| **Problem** | No authoritative formula |
| **Decision** | **Placeholder:** GPA is version-pinned derived metric (DL-004); **formula deferred to HD-01/HD-15** |
| **Alternatives** | Ship invented 4.0 · Percentage-only default without approval |
| **Consequences** | Cannot implement 3C.4 until HD-01 |
| **Security** | Recalc permission |
| **Integrity** | Golden vectors required |
| **Performance** | Persist annual GPA |
| **Historical** | Pin calc+policy versions |
| **Rollback** | Hide GPA until approved |
| **Relation to 3B/3B.1** | Inputs from current/finalized grades per HD-04/18 |
| **Open HDs** | **HD-01, HD-05, HD-06, HD-15** |
| **Approval state** | Pending — **blocked on HDs** |

---

### ADR-025 — Ranking Policy and Privacy

| Field | Content |
|-------|---------|
| **Status** | DRAFT / HUMAN APPROVAL REQUIRED |
| **Context** | Blueprint rank columns; comparative sensitivity |
| **Problem** | Ties + who can see whom undefined |
| **Decision** | Ranking via versioned snapshots (DL-005); separate `results.view_ranking`; **tie/scope/privacy = HD-08…10** |
| **Alternatives** | Live window functions only · Public student boards |
| **Consequences** | Snapshot jobs + authz tests |
| **Security** | Privacy matrix mandatory |
| **Integrity** | Ranks not academic SSOT |
| **Performance** | Prefer snapshots over hot live rank |
| **Historical** | Snapshot versions retained |
| **Rollback** | Disable endpoints |
| **Relation to 3B/3B.1** | Uses derived totals, not raw grade edits |
| **Open HDs** | **HD-08, HD-09, HD-10, HD-14** |
| **Approval state** | Pending — **blocked on HDs** |

---

### ADR-026 — Transcript Issuance and Immutability

| Field | Content |
|-------|---------|
| **Status** | DRAFT / HUMAN APPROVAL REQUIRED |
| **Context** | SHA-256 integrity docs; blueprint transcripts; 3D staging |
| **Problem** | Live print vs legal artifact conflict |
| **Decision** | Hybrid (DL-006); issuance in **Phase 3D** (HD-16); immutability preference **A** pending HD-11 |
| **Alternatives** | Always live · Auto-rewrite issued files |
| **Consequences** | Object storage + issue/revoke/supersede |
| **Security** | Issue/revoke permissions; school isolation |
| **Integrity** | file_hash verification |
| **Performance** | Async generation |
| **Historical** | Issued docs pinned to result/policy/calc versions |
| **Rollback** | Forward-fix after first issue |
| **Relation to 3B/3B.1** | Consumes official results; grade correct ≠ silent transcript rewrite (if A) |
| **Open HDs** | **HD-11, HD-12, HD-16** |
| **Approval state** | Pending |

---

### ADR-027 — Calculation Versioning and Rebuild-from-Grades

| Field | Content |
|-------|---------|
| **Status** | DRAFT / HUMAN APPROVAL REQUIRED |
| **Context** | Outbox messages marked processed; long-term rebuild needed |
| **Problem** | Event-only rebuild unreliable |
| **Decision** | Persist calc version + fingerprint (DL-008); rebuild from grades + immutable policies (DL-009/016) |
| **Alternatives** | Outbox replay-only · Full event sourcing |
| **Consequences** | Rebuild jobs; golden fingerprint tests |
| **Security** | Rebuild is privileged ops |
| **Integrity** | Deterministic equivalence |
| **Performance** | Batch/chunked rebuild |
| **Historical** | Compare fingerprints across versions |
| **Rollback** | Rebuild is additive/superseding |
| **Relation to 3B/3B.1** | Grades remain rebuild SSOT |
| **Open HDs** | HD-04, HD-18 |
| **Approval state** | Pending |

---

### ADR-028 — Results Consistency — Async Projections and Explicit Finalization

| Field | Content |
|-------|---------|
| **Status** | DRAFT / HUMAN APPROVAL REQUIRED |
| **Context** | ADR-008 eventual summaries; academic official freeze needed |
| **Problem** | Sync calc in grade handlers couples contexts |
| **Decision** | Mixed consistency (DL-013): async operational projections; **explicit Finalize*** for official; SLO per HD-13 |
| **Alternatives** | Sync everything · Nightly-only |
| **Consequences** | Stale flags; outbox consumers; finalize commands |
| **Security** | Finalize elevated |
| **Integrity** | Official versions immutable except supersede |
| **Performance** | Avoid HTTP-inline mass calc |
| **Historical** | Official timeline explicit |
| **Rollback** | Feature-flag consumers |
| **Relation to 3B/3B.1** | Grade handlers stay free of Results writes |
| **Open HDs** | HD-13, HD-14, HD-17, HD-18 |
| **Approval state** | Pending |

---

## 15. Security Gate

| Requirement | 3C.0 status |
|-------------|-------------|
| Extend Policy→SchoolContext→…→FORCE RLS to Results | Locked as DL-011 (proposed) |
| Separate ranking permission | Recommended; HD-10 |
| No Results bypass of tenant isolation | Compatible |
| Intelligence cannot invent policy | Confirmed |
| `results.*` permissions | Design only — not implemented |

---

## 16. Data Integrity Gate

| Requirement | 3C.0 status |
|-------------|-------------|
| No second grade ledger | DL-001 |
| No hard delete finalized/issued | DL-015 |
| Official supersession | DL-007 |
| Policy immutability | DL-014 |
| Fingerprints | DL-008 / DL-016 |

---

## 17. Historical Truth Gate

| Requirement | 3C.0 status |
|-------------|-------------|
| Grade correction does not silently rewrite official Results | DL-007 + async supersede path |
| Issued transcript behavior | **HD-11 required** |
| Policy change does not rewrite old official Results | DL-014 |

---

## 18. Rebuildability Gate

| Requirement | 3C.0 status |
|-------------|-------------|
| Rebuild from grades + policies | DL-009 |
| Deterministic equivalence | DL-016 |
| Not dependent solely on processed outbox | Confirmed by job evidence |

---

## 19. Performance / Scale Gate

| Requirement | 3C.0 status |
|-------------|-------------|
| No premature Results partitioning | DL-010 |
| Heavy recalc via queue | Compatible with sis-core |
| Ranking snapshots preferred | DL-005 |
| Evidence-driven later optimization | Adaptive governance |

---

## 20. Intelligence Governance Gate

| Allowed | Forbidden |
|---------|-----------|
| Detect stale projections | Auto-change grading policy |
| Recommend recalculation | Auto-issue/revoke transcripts |
| Perf/index recommendations | Auto-modify official academic truth |
| Anomaly alerts | Invent GPA/ranking rules |

---

## 21. Risks

| Risk | Level | Mitigation |
|------|-------|------------|
| Implementing before HD-01/11/18 | CRITICAL | Gate 3C.1+ on approvals |
| Treating blueprint sketch as final DDL | HIGH | Enrich at implement under DB skill |
| Invented GPA/letters by engineers | CRITICAL | ADR-024 blocked |
| Ranking privacy leak | HIGH | HD-10 |
| Outbox-only rebuild assumption | HIGH | DL-009/016 |
| Collapsing 3D issuance into rushed 3C | MEDIUM | HD-16 |
| Stale data-quality unique key confusion | MEDIUM | Prefer LIVE 3B |

---

## 22. Open Questions

1. Who is the institutional owner for GPA/letter/ranking policy sign-off?  
2. Will v1 ship without letters/GPA and only term totals?  
3. Are credit hours required data before any credit GPA?  
4. Legal counsel position on HD-11?  
5. Should term “closed” be an academic calendar feature before Results finalize automation?

---

## 23. Required Human Approvals

### A. Approve Design Locks

- [ ] DL-001 … DL-016 (including deterministic rebuild)

### B. Resolve Human Decisions (minimum before deep implement)

| Priority | IDs | Blocks |
|----------|-----|--------|
| P0 | HD-01, HD-04, HD-11, HD-16, HD-18 | Formula, official calc, transcript law, packaging, eligibility |
| P0 | HD-03, HD-05, HD-06, HD-07 | Term/annual correctness |
| P1 | HD-08, HD-09, HD-10, HD-14 | Ranking |
| P1 | HD-02, HD-12, HD-13, HD-15, HD-17 | Letters, retention, SLO, credits, calendar |

### C. ADR drafts

- [ ] ADR-021…028 reviewed; accept only after related HDs where blocked

### D. Authorization to start next phase

- [ ] Explicit approval: **Phase 3C.1 — Policy & Calculation Catalogs**

---

## 24. Phase 3C.1 Entry Preconditions

Phase 3C.1 may begin **only when**:

1. This Phase 3C.0 gate is accepted (PASS WITH CONDITIONS acknowledged).  
2. DL-001…DL-016 approved or formally amended in writing.  
3. At minimum HD-16 confirmed; **HD-01/HD-02/HD-03 enough to shape policy catalog columns** (or explicit “catalog skeleton without bands/GPA conversion yet”).  
4. Human issues: **IMPLEMENTATION AUTHORIZATION for Phase 3C.1 only**.  
5. Database-change skill + blueprint update process followed at implement time.  
6. No Results calculation/ranking/transcript issuance code in 3C.1 beyond catalogs (per 3C audit phase plan).

**Do not auto-start 3C.1.**

---

## 25. Final Gate

```text
PHASE 3C.0 GATE: PASS WITH CONDITIONS
```

### Conditions

- Human approval of DL-001…DL-016  
- Resolution or staged deferral plan for HD-01…HD-18 (P0 before calculation engines)  
- ADR-021…028 remain DRAFT until accepted  
- No implementation performed in 3C.0 (confirmed)  
- Phase 3B/3B.1 compatibility confirmed  
- Transcript 3C/3D boundary documented (HD-16)  
- Deterministic rebuild invariant documented (DL-016)

---

```text
IMPLEMENTATION AUTHORIZATION: NOT GRANTED
```

```text
NEXT PHASE: PHASE 3C.1 — POLICY & CALCULATION CATALOGS
```

```text
Phase 3C.1 MUST NOT be implemented until the human reviews this report and explicitly approves proceeding.
```

```text
Database changes: NONE
Migrations: NONE
API changes: NONE
UI changes: NONE
Application code changes: NONE
```

**STOP.** Await HUMAN REVIEW.
