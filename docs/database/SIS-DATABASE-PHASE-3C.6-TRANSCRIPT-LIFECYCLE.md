# SIS DATABASE — PHASE 3C.6  
# TRANSCRIPT LIFECYCLE

**Document type:** DESIGN ONLY  
**Date:** 2026-09-10  

```text
NO DDL · NO CODE
```

---

## 1. Dual Lifecycle Tracks (MANDATORY)

Live and Official are **separate tracks**. Generation of live content does **not** issue an official artifact.

### 1.1 Live Transcript

```text
NOT_CALCULATED
      ↓
CALCULATED
      ↓
STALE
      ↓
REBUILD_REQUIRED
```

| State | Meaning |
|-------|---------|
| NOT_CALCULATED | No current live projection |
| CALCULATED | Current live projection exists |
| STALE | Upstream sources/policies changed vs live fingerprint |
| REBUILD_REQUIRED | Rebuild mandated (ops/policy) before trusted live use |

Live may also carry `FAILED` technical marker.

### 1.2 Official Issued Transcript

```text
NOT_ISSUED
      ↓
FINALIZED          ← content frozen for issuance readiness
      ↓
ISSUED             ← official artifact exists
      ↓
SUPERSEDED         ← prior official retained
```

Optional publication track (orthogonal):

```text
UNPUBLISHED ↔ PUBLISHED
```

Publication may apply to ISSUED artifacts only (typical) — exact rules **HDR**.

---

## 2. Operation Separation

| Operation | Effect |
|-----------|--------|
| **Calculate / BuildLive** | Produces/refreshes live projection |
| **Finalize** | Freezes a TranscriptVersion as issuance-ready (not yet issued) |
| **Issue** | Creates immutable issued binding + artifact metadata |
| **Publish** | Makes issued artifact visible to approved audiences |
| **Supersede / Reissue** | New official version; prior SUPERSEDED per HD-11/12 |
| **RevokePublication** | Removes publication without deleting historical issued artifact |

| Myth | Truth |
|------|-------|
| CALCULATED = Official | **FALSE** |
| FINALIZED = ISSUED | **FALSE** |
| ISSUED = PUBLIC | **FALSE** |

---

## 3. Allowed Transitions (architectural)

| From | To | Track | Notes |
|------|----|-------|-------|
| NOT_CALCULATED | CALCULATED | Live | BuildLive / Rebuild |
| CALCULATED | STALE | Live | Upstream change detected |
| STALE | CALCULATED | Live | Successful rebuild |
| STALE | REBUILD_REQUIRED | Live | Policy/ops escalate |
| REBUILD_REQUIRED | CALCULATED | Live | Rebuild succeeds |
| NOT_ISSUED | FINALIZED | Official | FinalizeTranscript — elevated authz **HDR** |
| FINALIZED | ISSUED | Official | IssueTranscript — artifact + provenance required |
| ISSUED | SUPERSEDED | Official | Reissue/Supersede under HD-11/12 |
| ISSUED | PUBLISHED / UNPUBLISHED | Publication | HD privacy **HDR** |

### Forbidden

- Mutate ISSUED content in place  
- Hard delete ISSUED / SUPERSEDED (unless future explicit legal policy — **HD-12**; default **prohibit**)  
- Issue without finalized content + complete provenance  
- Cross-school issue/publish  
- Treat live CALCULATED as ISSUED  
- Rewrite historical presentation as if it were a new academic issuance without lineage  

---

## 4. Actor & Provenance Requirements

Official transitions require (conceptually):

- SchoolContext  
- Authorized actor / actor type  
- Correlation / request IDs  
- Reason (for supersede/reissue)  
- Pinned source / policy / calculation / content / presentation versions  
- Fingerprints  

Roles for finalize/issue = **HUMAN DECISION REQUIRED** (permission catalog later).

---

## 5. Source Version Requirements

| Action | Requirement |
|--------|-------------|
| Live calculate | Readable current approved sources |
| Finalize | Explicit pinned source set complete for content policy |
| Issue | Same pins frozen; artifact integrity metadata present |
| Historical explain | Pins + source set — **not** today’s live-only data |

---

## 6. Supersession Semantics

```text
Issued Transcript V1
  → academic correction / policy reissue
  → V2 ISSUED (if policy requires)
  → V1 SUPERSEDED
```

Both remain auditable. HD-11 chooses whether reissue is automatic, manual, or not required. Architecture supports all.

---

## 7. Retention Boundary (HD-12)

- Immutable historical versions  
- Supersession lineage  
- Retention metadata / archival state slots  
- Default: no hard delete of official history  

**Duration years:** **HUMAN DECISION REQUIRED** — not invented.

---

## 8. Correction Path (summary)

```text
Grade correction → outbox → impact
  → Live: STALE / rebuild
  → Issued: HD-11 policy (leave / supersede / reissue / approve)
```

Never silent in-place rewrite of V1.

---

## 9. Consistency (DL-013)

| Kind | Consistency |
|------|-------------|
| Live transcript | Eventual |
| Issued transcript | Immutable after issue |
| Publication views | Eventual relative to issue + privacy policy |
