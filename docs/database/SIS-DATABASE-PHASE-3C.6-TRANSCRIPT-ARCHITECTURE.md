# SIS DATABASE — PHASE 3C.6  
# TRANSCRIPT ARCHITECTURE

**Document type:** AUDIT + ARCHITECTURE + DESIGN LOCK ONLY  
**Date:** 2026-09-10  
**Predecessor:** Phase 3C.5 Gate (`PASS WITH CONDITIONS`)  
**Status:** ARCHITECTURE DESIGNED — issuance/retention/content policies remain UNRESOLVED  

```text
NO DDL · NO MIGRATIONS · NO APPLICATION CODE · NO PDF/RENDER/STORAGE
IMPLEMENTATION AUTHORIZATION: NOT GRANTED
```

---

## 1. Mission Statement

```text
TRANSCRIPT
```

is a **consumer/projection** of governed academic Results — **not** academic SSOT.

### Mandatory lock (DL-006)

```text
Transcript = Hybrid Live Projection + Immutable Issued Artifact
```

| Concept | Nature |
|---------|--------|
| **Live Transcript** | Current reconstructible projection; eventual; refreshable; not official merely by generation |
| **Issued Official Transcript** | Versioned immutable historical artifact; superseded not mutated; auditable; reproducible from pinned sources |

**Do not collapse A and B.**

---

## 2. Canonical Dependency (consumer, not serial choke)

```text
exams.student_grades (SSOT)
  ↓
Term Results / Annual Results / GPA  (versioned derived)
  ↓
Ranking (optional consumer — not required by default)
  ↓
Transcript (Live projection and/or Issued artifact)
```

Transcript **MAY** consume independently pinned versions of Term, Annual, GPA, approved ranking, and academic metadata — without forcing an unnecessarily serial pipeline.

**FORBIDDEN write-back:** Transcript → grades / Term / Annual / GPA / Ranking.

---

## 3. Audit of Existing Transcript Mentions

| Location | Finding | Classification |
|----------|---------|----------------|
| Blueprint `results.transcripts` | `student_id`, `academic_year_id`, `transcript_number`, `storage_key`, `file_hash`, `generated_*` — **no `school_id`**, no versioning, no live vs issued, no source pins | **Stale sketch** |
| Phase 3A/3B gates | `results.transcripts` **not created** (LIVE absent) | **Authoritative LIVE** |
| Phase 3C.0 DL-006 | Hybrid live + immutable issued | **Authoritative lock** |
| Phase 3C.0 HD-11 | Options A immutable+supersede vs B auto-change issued | **Unresolved** (arch preference A not approved) |
| Phase 3C.0 HD-12 | Retention/supersession | **Unresolved** |
| Phase 3C.1 HD-16 | 3C design / 3D issuance packaging | **Deferred** (await confirm) |
| Phase 3A “transcripts → Phase 3D” | Packaging/issuance deferred | **Aligned with HD-16** — 3C.6 designs contracts only |
| `security-audit-resilience.md` SHA-256 for certificates/transcripts | Integrity pattern | **Authoritative pattern candidate** — not full transcript SSOT |
| `DATA-LIFECYCLE-MATRIX` certificates permanent | Certificates domain | **Related example** — not transcript retention policy |
| `api-conventions` certificate verify path | Certificate verification API sketch | **Future boundary** — not transcript policy |
| Annual/GPA/Ranking embedding GPA+rank+credits | Blueprint annual row | **Stale conflation** — not Transcript Content Contract |

```text
AUTHORITATIVE FOR DESIGN: DL-006 + Phase 3C.0–3C.6
NON-AUTHORITATIVE AS SSOT: blueprint results.transcripts sketch
LIVE: results.transcripts table ABSENT
```

---

## 4. Core Questions (architectural answers)

| Concern | Answer | Status |
|---------|--------|--------|
| What is transcript? | Hybrid live projection + issued artifact | **LOCKED (DL-006)** |
| Academic SSOT? | Never — grades remain SSOT | **LOCKED** |
| Content fields mandatory? | Policy-driven Content Contract slots | Values **HDR** |
| Correction after issue? | Impact analysis + HD-11 policy slots | **HDR (HD-11)** |
| Retention? | Immutable history + supersession semantics; duration **HDR (HD-12)** | Structure **LOCKED** |
| 3C vs 3D? | 3C architecture/contracts; issuance packaging Phase 3D | **Deferred (HD-16)** |
| Calculate ≠ Finalize ≠ Issue ≠ Publish | Explicit separation | **LOCKED** |
| Artifact format? | Abstract TranscriptArtifact | Technology **HDR** |
| Ranking on transcript? | Optional; pin RankingVersion if included | **HDR** |
| GPA on transcript? | Optional; pin GPA version; never recalculate | **HDR** |

---

## 5. Transcript Identity (PROPOSED)

Business identity is **not** assumed to be one-student-one-transcript.

**PROPOSED flexible identity axes:**

```text
school_id
student_id
transcript_kind          -- live_projection | issued_family | … (logical)
scope_definition_id      -- content/policy definition version
period_coverage_type     -- single_year | multi_year | enrollment_span | custom — HDR
period_coverage_ref      -- typed anchors (year ids, enrollment ids, …)
enrollment_id?           -- when enrollment-scoped
program_id?              -- when program-scoped — HDR
```

**Design for (policy undecided):** multi-year education, transfers, program changes, multiple enrollments, repeated periods, partial history.

Storage identity (surrogate PK) ≠ business identity ≠ artifact identity.

---

## 6. Content Contract (summary)

See Content Contract artifact.

Slots include: identity, school, period, program, result sections, academic units (subject/course/module/… extensible), grades, statuses, credits, GPA, ranking, standing, completion, admin/issuance metadata.

Classify each as: mandatory architectural slot · optional policy field · derived · copied source · presentation-only.

**Do not** declare GPA/rank/credits/letters mandatory merely because common.

---

## 7. Source Provenance

```text
Transcript Version
  ↓
Transcript Source Set
  ├── Term Result Version(s)
  ├── Annual Result Version(s)
  ├── GPA Version(s)
  ├── Ranking Version(s) (if included)
  └── Academic metadata versions
```

Exact membership = **policy-driven**. Every academically meaningful value traces to a governed source version.

---

## 8. Live vs Issued

### Live
Reconstructible; refresh/rebuild; stale detection; correction propagation; school-isolated; **not** immutable official by generation alone.

### Issued
Immutable after issuance; versioned; provenance-preserving; superseded not mutated; reproducible from **pinned** historical sources — **not** from “today’s” live DB alone.

```text
Source Academic Data → Projection → Transcript Version → Issued Artifact
```

---

## 9. Calculate / Finalize / Issue / Publish

```text
CALCULATED → FINALIZED → ISSUED → PUBLISHED
```

| Myth | Reality |
|------|---------|
| CALCULATED = Official | **FALSE** |
| FINALIZED = ISSUED | **FALSE** |
| ISSUED = PUBLIC | **FALSE** |

---

## 10. Artifact Architecture

Abstract `TranscriptArtifact`: type, media type, content fingerprint, creation time, transcript version, storage reference (opaque), integrity metadata.

**Not selected:** PDF/HTML/JSON/DOCX/signed PDF/blockchain.

Storage refs ≠ authorization. Retrieval requires authz + school isolation + audit.

---

## 11. Content vs Presentation

```text
Academic Truth (upstream)
  ↓
Transcript Content Projection
  ↓
Transcript Presentation Definition (versioned)
  ↓
Artifact Renderer (future)
  ↓
Issued Artifact
  ↓
Publication Channel
```

Template T1 provenance retained on issued artifacts. Template T2 does not rewrite V1 academic content. Distinguish content version · presentation version · artifact version · issuance version.

---

## 12. Correction After Issuance (HD-11)

```text
Grade correction → Outbox → Impact → Live rebuild
  → Determine issued impact
  → Apply approved HD-11 policy
  → Optionally: new version / supersede / reissue / manual approval
```

Architecture **supports** all options; **does not select** A vs B.

V1 never mutated in place.

---

## 13. School Isolation & Artifact Security

```text
SchoolContext → Command/Query → Transcript Identity → Composite FK → RLS → FORCE RLS
```

Fail closed. No cross-school source/artifact access. Opaque storage keys. Download/publication/verification audits (design). Revoked/superseded handling without deleting history (HD-12 duration HDR).

---

## 14. Privacy / Verification (slots only)

Audiences: student, parent/guardian, teacher, admin, registrar, external verification, public — **HDR**.

Verification boundary: verification id, fingerprints, status, supersession, privacy-preserving response — **no** QR/blockchain/public API invented.

---

## 15. CQRS (conceptual)

**Commands:** BuildLiveTranscript, RebuildTranscript, FinalizeTranscript, IssueTranscript, SupersedeTranscript, PublishTranscript, RevokePublication, ReissueTranscript  

**Queries:** GetLiveTranscript, GetIssuedTranscript, GetTranscriptHistory, GetTranscriptVersion, GetArtifactMetadata, VerifyTranscript  

Separate command · read · artifact/publication sides. No implementation.

---

## 16. Concurrency Controls (five mechanisms)

| Mechanism | Role |
|-----------|------|
| Idempotency | Duplicate issue/rebuild does not create unintended official duplicates |
| Concurrency control | Serialize per transcript identity (FUTURE) |
| Version conflict detection | Stale writers blocked |
| Fingerprint equivalence | Unchanged content/source detection — **not** sole idempotency |
| Artifact identity | Prevents duplicate official artifacts |

---

## 17. Performance Blueprint

Access: live current, historical issued, student history, generate/rebuild, mass regen, correction fan-out, artifact get, verify, publish; large populations; long retention.

```text
NO INDEXES · NO PARTITIONS THIS PHASE (DL-010)
```

Future indexes from measured workload + EXPLAIN ANALYZE.

---

## 18. Failure Model

Fail closed for: missing/inconsistent sources; superseded mandatory sources without policy; missing policy/calc; incomplete dataset; unauthorized/concurrent/duplicate issuance; artifact create/store failure; privacy mismatch; school-context failure; fingerprint mismatch.

**Never:** partial official issuance; silent source/policy substitution.

---

## 19. Boundaries

| Boundary | Rule |
|----------|------|
| Annual | Consume pinned versions; impact on change; never mutate |
| Term | Same; extensible academic units |
| GPA | Optional content; pin version; never recalculate |
| Ranking | Optional; pin snapshot; never calculate; not required by default |
| Graduation | Future consumer (3C.7); HD-16 packaging boundary |

---

## 20. Completeness Layers (do not collapse)

1. Source existence · 2. Validity · 3. Completeness · 4. Transcript eligibility · 5. Inclusion · 6. Presentation availability · 7. Artifact readiness · 8. Issuance readiness · 9. Publication readiness  

Mandatory field set = **HDR**.

---

## 21. Traceability (summary)

| Item | Status |
|------|--------|
| DL-001…016 | **Preserved** (esp. DL-006) |
| HD-11, HD-12 | **Unresolved** — architecture supports future policy |
| HD-16 | **Deferred** |
| HD-01…10, 14–15, 18, Rounding | **Unresolved dependencies** for optional content |

---

## 22. Architectural Q&A Gate

| Check | Answer |
|-------|--------|
| Hybrid live + issued? | **YES** |
| Transcript non-SSOT? | **YES** |
| HD-11/12 invented? | **NO** |
| Content/presentation separated? | **YES** |
| Calculate≠Finalize≠Issue≠Publish? | **YES** |
| Implementation? | **NO** |
