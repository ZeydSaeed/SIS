# SIS DATABASE — PHASE 3C.6  
# TRANSCRIPT DATA MODEL (LOGICAL)

**Document type:** LOGICAL MODEL — NOT DDL  
**Date:** 2026-09-10  

```text
LOGICAL ENTITY ≠ PHYSICAL TABLE
NO CREATE TABLE · NO INDEXES · NO RLS SQL
```

---

## 1. Principle

```text
STALE: blueprint results.transcripts (flat row, no school_id, no live/issued split, no source pins)
AUTHORITATIVE DESIGN: DL-006 hybrid model + this logical catalog
LIVE SCHEMA: results.transcripts ABSENT
```

Ownership: **Results** bounded context (DL-012); issuance packaging may align with Phase 3D (HD-16).  
SSOT: grades only. Transcript never owns academic truth.

---

## 2. Logical Concepts

### 2.1 Transcript (business identity)

| Aspect | Definition |
|--------|------------|
| Purpose | Stable family identity for a student’s transcript under a scope/kind |
| Identity (PROPOSED) | school_id + student_id + transcript_kind + scope_definition_id + period coverage (+ optional enrollment/program) |
| Grain | Not assumed 1:1 with academic_year |
| SSOT? | **NO** |
| school_id | Mandatory |

### 2.2 TranscriptVersion

| Aspect | Definition |
|--------|------------|
| Purpose | One calculated/finalized/issued content instance |
| Identity | Transcript + transcript_version (monotonic) |
| Tracks | Live marker and/or Official lifecycle state |
| Immutable when ISSUED | Academic content + pins YES |
| Mutable | Publication flags (per policy); never issued academic payload |

### 2.3 TranscriptSourceSet / TranscriptSourceItem

| Aspect | Definition |
|--------|------------|
| Purpose | Exact governed sources used |
| Captures | Term/Annual/GPA/Ranking/metadata version refs, inclusion, role in content |
| Required for official | **YES** |
| Grain | Per TranscriptVersion |

### 2.4 TranscriptContent / TranscriptContentItem

| Aspect | Definition |
|--------|------------|
| Purpose | Structured academic content projection (not presentation) |
| Captures | Sections/items: periods, academic units, grades/statuses, optional credits/GPA/rank/standing/completion slots |
| Values | Copied/derived from pinned sources — field set **HDR** |
| Extensibility | subject/course/module/competency/training unit |

### 2.5 TranscriptEligibility

| Aspect | Definition |
|--------|------------|
| Purpose | Layered readiness: source existence → publication readiness |
| Related | HD-18 and content policy — values **HDR** |

### 2.6 TranscriptProvenance

| Aspect | Definition |
|--------|------------|
| Purpose | Actor, timestamps, correlation/causation, fingerprints, reasons |
| Official | Required |

### 2.7 TranscriptArtifact

| Aspect | Definition |
|--------|------------|
| Purpose | Abstract issued representation metadata |
| Captures | artifact_type, media_type, content/artifact fingerprints, created_at, opaque storage_ref, integrity metadata |
| Technology | **Not selected** |
| Authz | storage_ref ≠ permission |

### 2.8 TranscriptIssuance

| Aspect | Definition |
|--------|------------|
| Purpose | Binding of TranscriptVersion to issued status + issuer + issued_at + transcript_number/identifier slots |
| Immutable after issue | Core issuance facts |
| Numbering policy | **HDR (HD-12 related)** |

### 2.9 TranscriptSupersession

| Aspect | Definition |
|--------|------------|
| Purpose | predecessor/successor, reason, actor, timestamps |
| Retention | Both versions retained by default |

### 2.10 TranscriptPublication

| Aspect | Definition |
|--------|------------|
| Purpose | Audience visibility separate from issuance |
| Policy | Privacy **HDR** |
| ISSUED ≠ PUBLISHED | **LOCKED** |

### 2.11 TranscriptPresentationVersion

| Aspect | Definition |
|--------|------------|
| Purpose | Template/layout/localization version |
| Rule | Cannot silently alter historical academic content of issued Vn |
| Pin on artifact | Required for issued explainability |

### 2.12 TranscriptVerification (optional future)

| Aspect | Definition |
|--------|------------|
| Purpose | External verification boundary metadata |
| Policy/tech | **HDR** — no QR/blockchain/public API invented |

---

## 3. Relationships

```text
Transcript 1──* TranscriptVersion
TranscriptVersion 1──1 TranscriptSourceSet
TranscriptSourceSet 1──* TranscriptSourceItem
TranscriptVersion 1──1 TranscriptContent
TranscriptContent 1──* TranscriptContentItem
TranscriptVersion ── TranscriptEligibility / Provenance
TranscriptVersion 0..1 TranscriptIssuance
TranscriptIssuance 0..* TranscriptArtifact
TranscriptVersion ── TranscriptPublication
TranscriptVersion / Artifact ── PresentationVersion pin
TranscriptVersion ── Supersession lineage
```

---

## 4. Mutability Matrix

| Concept | Live | Official ISSUED |
|---------|------|-----------------|
| Content payload | Refreshable via new live calc | Immutable |
| Source set | May change on rebuild | Frozen |
| Presentation | May change for live | Historical pin retained |
| Publication | N/A or preview **HDR** | Mutable publish flag ≠ content mutate |
| Artifacts | None (or draft **HDR**) | Immutable bytes/metadata |

---

## 5. Retention Implications

Official versions + artifacts + provenance retained for audit/reproducibility. Duration = **HD-12 HDR**. Default architectural posture: **no hard delete** of official history (DL-015).

---

## 6. Provenance Path (“Why these exact values?”)

```text
Issued artifact / content item
 ↓
TranscriptVersion
 ↓
SourceSet → Term/Annual/GPA/Ranking/metadata versions
 ↓
Policy + calculation + content + presentation versions
 ↓
Eligibility decisions
 ↓
Fingerprints (source, policy, calculation, content, artifact, transcript)
```
