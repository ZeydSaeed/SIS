# SIS DATABASE — PHASE 3C.6  
# TRANSCRIPT CONTENT CONTRACT (TECHNOLOGY-NEUTRAL)

**Document type:** DESIGN ONLY  
**Date:** 2026-09-10  
**Related:** Phase 3C.1 Policy Catalog §5.10 · DL-006  

```text
NO RENDERER · NO PDF · NO FIELD POLICY INVENTION
```

---

## 1. Purpose

Define how a Transcript Version references approved academic components **without** inventing which fields are mandatory or how they are graded/ranked.

```text
Approved Source Versions
+ Approved Policy Versions
+ Approved Calculation Versions
+ Approved Transcript Content Policy
+ Approved Presentation / Artifact Version
=
Transcript Version
```

---

## 2. Field Classification

Every slot is one of:

| Class | Meaning |
|-------|---------|
| **A — Mandatory architectural slot** | Identity/tenant/provenance scaffolding required for any official transcript |
| **B — Optional policy-controlled field** | Included only if content policy says so |
| **C — Derived field** | Computed from pinned sources under approved rules |
| **D — Copied source value** | Snapshot of a pinned upstream value |
| **E — Presentation-only** | Labels, layout, localization — not academic truth |

---

## 3. Contract Slots

### 3.1 Transcript identity — Class A
School, transcript family identity, transcript_version, kind (live vs official path).

### 3.2 Student identity — Class A (core) / B (display attributes)
Stable student id; display name/ID numbers as presentation copies with source metadata — PII handling **HDR**.

### 3.3 School identity — Class A
school_id + school display metadata copies.

### 3.4 Academic period — Class A structure / B coverage policy
Typed period coverage (single year, multi-year, enrollment span, …) — **how coverage is chosen = HDR**.

### 3.5 Program / specialization — Class B
Only if applicable and approved.

### 3.6 Academic result sections — Class A structure / B content
Containers for term/year/sections; membership **HDR**.

### 3.7 Academic unit representation — Class A extensibility
subject | course | module | competency | training unit | other approved unit — do not freeze only `subject_id`.

### 3.8 Grade representation — Class B/D
Scores/letters/status from pinned Term/Annual/grade sources — letter bands **HD-02**; status eligibility **HD-04/05**.

### 3.9 Result status — Class B/D
Pass/fail/incomplete/etc. — semantics **HDR**.

### 3.10 Credit representation — Class B/D
**HD-15 unresolved** — optional slot only.

### 3.11 GPA representation — Class B/D
Term/annual/cumulative GPA only if content policy includes them; **must pin GPA version**; never recalculate (**HD-01**).

### 3.12 Ranking representation — Class B/D
Rank/percentile/cohort only if explicitly included; **must pin RankingVersion**; never calculate; not default-required (**HD-08…10, HD-14**).

### 3.13 Academic standing — Class B
Standing codes — **HDR** / future graduation linkage.

### 3.14 Completion / graduation — Class B
Boundary to Phase 3C.7; not invented here (**HD-16** related).

### 3.15 Administrative metadata — Class A/B
Generated/finalized actors, timestamps, correlation ids, reasons.

### 3.16 Issuance metadata — Class A for issued
issued_at, issuer, transcript_number/identifier slots, supersession refs — numbering **HD-12 HDR**.

---

## 4. Provenance Rule

For every Class B/C/D academically meaningful value:

```text
value → TranscriptContentItem → SourceItem → upstream version pin
```

Missing mandatory (per **approved** content policy) source ⇒ **fail closed** for finalize/issue.

Which fields are mandatory = **HUMAN DECISION REQUIRED** (content policy) — not invented here.

---

## 5. Fingerprints (logical)

| Fingerprint | Covers |
|-------------|--------|
| Source | SourceSet membership + upstream version ids + included values canonical form |
| Policy | Content + eligibility + related academic policy versions |
| Calculation | calculation_version(s) used in upstream/projection |
| Content | Structured TranscriptContent canonical serialization |
| Artifact | Issued bytes/representation integrity |
| Transcript | Aggregate of the above for the version |

**Algorithm:** FUTURE IMPLEMENTATION REQUIREMENT (SHA-256 noted as existing integrity *pattern* in security docs — not mandated as sole algorithm here).

---

## 6. Completeness Layers

1. Source existence  
2. Source validity  
3. Source completeness  
4. Transcript eligibility  
5. Inclusion decisions  
6. Presentation availability  
7. Artifact generation readiness  
8. Issuance readiness  
9. Publication readiness  

Do not collapse to one `ready` flag.

---

## 7. Non-goals

- Mandating GPA/rank/credits/attendance/conduct on all transcripts  
- Selecting PDF/HTML/etc.  
- Inventing legal text blocks  
- Recalculating GPA/ranking inside transcript  
- Using current live DB alone to “explain” historical issued content  
