# SIS DATABASE — PHASE 3C.7  
# GRADUATION / COMPLETION LIFECYCLE

**Document type:** DESIGN ONLY  
**Date:** 2026-09-10  

```text
NO DDL · NO CODE
```

---

## 1. Separate State Families

Do not collapse into one enum.

| Family | Meaning |
|--------|---------|
| **Calculated / Evaluation** | Machine evaluation of requirements |
| **Eligibility / Completion** | Academic requirement outcome |
| **Approval** | Institutional authority |
| **Award** | Official recognition |
| **Publication** | Downstream visibility |

Graduation approval ≠ transcript publication.

---

## 2. Candidate Lifecycle (architectural)

```text
NOT_EVALUATED
      ↓
EVALUATED
      ↓
ELIGIBLE / COMPLETED / NOT_ELIGIBLE   ← completion track
      ↓
PENDING_APPROVAL
      ↓
APPROVED
      ↓
AWARDED / GRADUATED
```

Historical: **SUPERSEDED** / **CORRECTED** / **REVOKED** (if policy allows — **HD-35/36**).

Institutions may omit states; do not force all.

---

## 3. Requirement Evaluation Statuses (candidate)

NOT_EVALUATED · EVALUATED · SATISFIED · NOT_SATISFIED · EXEMPT · NOT_APPLICABLE · PENDING_EVIDENCE · BLOCKED · SUPERSEDED  

Validate sufficiency at policy time; additional states may be required (**HDR**).

---

## 4. Operational vs Official

| Layer | Nature |
|-------|--------|
| Operational evaluation | Rebuildable working calc; may be stale |
| Official completion result | Finalized governed outcome |
| Graduation approval | Authorized decision |
| Graduation award | Official recognition record |
| Published result | What users/systems may see |

---

## 5. Transitions (architectural)

| From | To | Actor | Notes |
|------|----|-------|-------|
| NOT_EVALUATED | EVALUATED | System | EvaluateCompletion |
| EVALUATED | ELIGIBLE/COMPLETED/NOT_ELIGIBLE | System + policy | Deterministic under pins |
| ELIGIBLE/COMPLETED | PENDING_APPROVAL | System/human | If approval required (HD-31) |
| PENDING_APPROVAL | APPROVED | Human authority | Fail closed if unauthorized |
| APPROVED | AWARDED | Human/system under policy | Award semantics HD-32 |
| AWARDED | SUPERSEDED | Correction/reissue | HD-35/36 — not silent |
| * | STALE / REBUILD_REQUIRED | Upstream change | Ops markers |

### Forbidden

- Silent mutate AWARDED/APPROVED official payload  
- Hard delete official history (default; HD-37)  
- Auto-award on Annual FINALIZED / Transcript ISSUED / Ranking FINALIZED / Year closed  
- Cross-school approve  
- Treat StudentStatus::Graduated flip as the approval itself  

---

## 6. Dates

| Date | Distinct? |
|------|-----------|
| Completion date | YES — **HD-34** |
| Graduation date | YES — **HD-33** |
| Approval timestamp | YES |
| Award timestamp | YES |
| Academic year close date | Separate calendar decision |

Do not invent which date is “legal.”

---

## 7. Human Workflow (permitted model)

```text
Machine calculation → Human review → Human approval → Official award
```

Alternative approved models later allowed; do not hard-wire a single institutional process as universal law. Default: **no automatic graduation**.

---

## 8. Correction After Award

```text
Upstream correction → impact → new evaluation version
  → candidate supersession of completion/graduation
  → human action per HD-35/36
  → StudentStatus / transcript / certificates impact as projections
```

Not automatic revoke.

---

## 9. Publication

Publication of graduation status is orthogonal to award. Privacy/publication = **HD-38**.

---

## 10. Separation from Other Closures

| Event | Implies Graduated? |
|-------|-------------------|
| Academic year closed | NO |
| Enrollment completed (admin) | NO (unless policy — HDR) |
| Transcript issued | NO |
| GPA finalized | NO |
| Ranking finalized | NO |
| Promotion recorded | NO |
| Certificate issued | Downstream consumer, not creator of graduation truth |
