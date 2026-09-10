# SIS DATABASE — PHASE 3C.6  
# TRANSCRIPT REBUILD & CORRECTION ARCHITECTURE

**Document type:** DESIGN ONLY  
**Date:** 2026-09-10  

```text
NO JOBS · NO CODE · NO DDL · NO RENDERER
```

---

## 1. Reconstruction Contract

```text
Approved Source Versions
+ Approved Policy Versions
+ Approved Calculation Versions
+ Approved Transcript Content Policy
+ Approved Presentation / Artifact Version
=
Transcript Version
```

Technology-neutral. Mathematical/display field rules remain **HDR**.

---

## 2. Live Rebuild Flow

```text
Detect upstream change (grades / Term / Annual / GPA / optional Ranking)
      ↓
Mark Live STALE / REBUILD_REQUIRED
      ↓
Resolve SchoolContext
      ↓
Reconstruct SourceSet from current approved versions (per live policy)
      ↓
Evaluate completeness / eligibility layers
      ↓
Build TranscriptContent projection
      ↓
Update live CALCULATED + fingerprints
```

Live rebuild **does not** issue an artifact and **does not** mutate issued history.

---

## 3. Official Finalize / Issue Flow

```text
Select/pin SourceSet (explicit versions)
      ↓
Validate completeness per approved content policy
      ↓
Finalize TranscriptVersion (content frozen)
      ↓
Issue → create issuance + TranscriptArtifact metadata
      ↓
Optional Publish (privacy policy HDR)
```

Fail closed if mandatory sources/policies missing. No partial issuance.

---

## 4. Historical Reproducibility

To answer “why these exact values when issued?”:

```text
TranscriptVersion
+ SourceSet
+ Policy versions
+ Calculation versions
+ Eligibility decisions
+ Content definition
+ Presentation version
+ Artifact metadata / fingerprints
```

**FORBIDDEN:** regenerating historical issued content **solely** from today’s live academic data.

---

## 5. Grade Correction After Issuance (HD-11)

```text
Grade correction (VOID+INSERT)
      ↓
Outbox event (trigger only)
      ↓
Impact detection → Annual / GPA / Ranking / Transcript identities
      ↓
Reconstruct Live Transcript (STALE → rebuild)
      ↓
Determine whether any ISSUED transcript is affected
      ↓
Apply approved HD-11 policy:
   · leave historical artifact unchanged
   · mark SUPERSEDED
   · issue replacement V2
   · require manual approval
   · change live only
      ↓
If reissue: new TranscriptVersion + supersession lineage
```

**Architecture supports all options. Policy selection = HUMAN DECISION REQUIRED.**

V1 never overwritten in place.

---

## 6. Upstream Change Impact (non-grade)

| Change | Live | Issued |
|--------|------|--------|
| Annual Result supersession | Rebuild live | Impact analysis → HD-11-like policy slots |
| GPA supersession | Rebuild if GPA included | Same |
| Ranking supersession | Rebuild only if ranking included | Same |
| Content/presentation policy change | Live may refresh | Issued unchanged unless reissue policy |

---

## 7. Fingerprints

Source · Policy · Calculation · Content · Artifact · Transcript aggregate — see Content Contract. Algorithm FUTURE.

---

## 8. Control Mechanisms

| Mechanism | Role |
|-----------|------|
| Idempotency | Duplicate Issue/Rebuild commands |
| Concurrency | Serialize per transcript identity (FUTURE) |
| Version conflict detection | Stale finalize/issue writers |
| Fingerprint equivalence | Detect unchanged content — not sole idempotency |
| Artifact identity | Prevent duplicate official artifacts |

---

## 9. Modes

| Mode | Purpose |
|------|---------|
| Live refresh | CALCULATED current projection |
| Official finalize | Frozen issuance-ready version |
| Issue | Immutable artifact binding |
| Reissue / supersede | V2 under HD-11/12 |
| Verification rebuild | Compare fingerprints; alert — no silent overwrite of issued |
| Publish / revoke publish | Visibility only |

---

## 10. Blockers for Official Issuance Implementation

Blocked without human-approved:

- HD-11 (correction-after-issue behavior) for production correction workflows  
- HD-12 (retention/numbering/supersession ops policy) for legal retention claims  
- Transcript content policy (which fields mandatory)  
- Upstream readiness for included components (GPA/letters/credits/ranking as applicable)  
- HD-16 confirmation of 3C vs 3D packaging boundary for issuance delivery  

Until then: architecture holds; implementation must refuse inventing legal/academic defaults.
