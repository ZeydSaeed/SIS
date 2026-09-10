# SIS DATABASE — PHASE 3C.9  
# HISTORICAL & PROVENANCE MODEL

**Document type:** LOGICAL ONLY  
**Date:** 2026-09-10  

```text
fingerprint ≠ idempotency key
Official history: supersede / revoke — never silent mutate / hard delete
```

---

## 1. Reproducibility Question

> Why was this student considered eligible / completed / graduated at that historical point?

Answer path:

```text
GraduationAwardVersion / CompletionOutcomeVersion
  → eligibility_policy_version_id
  → requirement_definition_version_ids (via RequirementEvaluations)
  → calculation_version
  → EvidenceSet / EvidenceItems (typed source versions)
  → enrollment_id + school_id (+ denorm year/specialization as of evaluation)
  → GraduationApproval (actor_ref, decided_at, decision)
  → OutcomeSupersession / RevocationRecord if any
  → source_fingerprint + policy_fingerprint (complement)
```

Historical explanation **must not** rely only on today’s live grades or current policy rows.

---

## 2. Correction Lifecycle (HD-35)

```text
Official Outcome V1 (completion and/or award)
       ↓
Upstream correction (grade/result/GPA…)
       ↓
Impact detection (outbox) — PROPOSED event names
       ↓
New CompletionOutcomeVersion candidate
       ↓
Human review / approval path (HD-31)
       ↓
Explicit supersession if required
       ↓
Official Outcome V2
```

V1 retained. No auto-revoke on grade change.

---

## 3. Revocation Lifecycle (HD-36)

```text
Issued AwardVersion
  → authorized human RevocationRecord
  → award status REVOKED (logical)
  → original payload preserved
  → authority_ref, reason_ref, timestamp, lineage
```

Roles/reasons = opaque refs until institution policy.

---

## 4. Temporal Fields (logical)

| Instant | On |
|---------|-----|
| evaluated_at | CompletionOutcomeVersion |
| eligibility_determined_at | CompletionOutcomeVersion |
| approval_requested_at / decided_at | GraduationApproval |
| awarded_at | GraduationAwardVersion |
| superseded_at | Supersession / version flags |
| revoked_at | RevocationRecord |
| policy effective_from/to | EligibilityPolicyVersion |

Graduation_date / completion_date semantic meaning = **HD-33/34 still open** — optional date attributes without inventing legal meaning.

---

## 5. Provenance Minimum Set (official)

| Element | Required |
|---------|----------|
| Scope (school, enrollment) | YES |
| Policy version | YES |
| Requirement versions used | YES |
| Evidence set | YES |
| Calculation version | YES |
| Evaluation version id | YES |
| Approval actor_ref (when awarded) | YES when approval path used |
| Fingerprints | YES as complement |
| Correlation / causation ids | YES (platform) |

---

## 6. Idempotency vs Fingerprint

| Mechanism | Role |
|-----------|------|
| Idempotency keys | Duplicate command/outbox suppression |
| Concurrency / version conflict | Stale writer protection |
| Fingerprint equivalence | Detect unchanged evaluation inputs |
| Surrogate version numbers | Lineage identity |

Do not use fingerprint alone as idempotency key.
