# SIS DATABASE — PHASE 3C.9  
# LOGICAL SCHEMA DESIGN  
# COMPLETION / GRADUATION

**Document type:** LOGICAL SCHEMA DESIGN ONLY  
**Date:** 2026-09-10  
**Authoritative decisions:** Phase 3C.8B (HD-19…22,31,32,35,36,39; DL-017…022 ACCEPTED)  

```text
MODE = LOGICAL SCHEMA DESIGN ONLY
IMPLEMENTATION AUTHORIZATION = NOT GRANTED
PHYSICAL DATABASE / MIGRATIONS / DDL / SQL / RLS / APP CODE = FORBIDDEN
```

---

## 1. Mission

Logical data model for **Completion** and **Graduation** as distinct versioned derived outcomes that consume upstream academic evidence without becoming SSOT for grades, results, GPA, ranking, or transcript.

```text
Completion ≠ Graduation
StudentStatus::Graduated = projection only
```

---

## 2. Design Principles

| Principle | Application |
|-----------|-------------|
| DL-017 | Separate CompletionOutcome family vs GraduationAward family |
| DL-018 | No copied grade/result SSOT inside graduation |
| DL-019 | Official versions immutable; supersession/revocation lineage |
| DL-020 | Evidence items required; missing ≠ satisfied |
| DL-021 | GPA optional/explicit only; no required GPA FK |
| DL-022 | StudentStatus sync is projection/event, not store of truth |
| HD-39 | Identity = school + enrollment (+ program/context from enrollment) |
| Not one table per noun | Collapse states into version attributes where grain allows |

---

## 3. Conceptual Layers & Chosen Entities

### 3.1 Policy layer

| Logical entity | Keep separate? | Reason |
|----------------|----------------|--------|
| **EligibilityPolicy** | YES | Stable policy family identity |
| **EligibilityPolicyVersion** | YES | Published immutable content pin (DRAFT/PUBLISHED/EFFECTIVE/RETIRED) |
| **RequirementDefinition** | YES | Requirement within a policy family |
| **RequirementDefinitionVersion** | YES | Versioned rule/unit spec without inventing values |
| Rule Definition | **Embed** in RequirementDefinitionVersion as structured rule payload / typed rule rows only if N rules explode — start as structured attributes + optional RuleElement child if multi-rule |

**No policy values seeded.** Thresholds remain empty until institution policy.

### 3.2 Evidence layer

| Logical entity | Keep separate? | Reason |
|----------------|----------------|--------|
| **EvidenceSet** | YES (1:1 with CompletionOutcomeVersion) | Reconstructible set for a version |
| **EvidenceItem** | YES | Typed `source_type` + `source_id` + optional `source_version_ref` |
| Evidence Source / Source Set | **Reuse** EvidenceItem + provenance on version; do not duplicate Grades/Results |

### 3.3 Completion layer

| Logical entity | Keep separate? | Reason |
|----------------|----------------|--------|
| **CompletionOutcome** | YES | Business identity (stable) |
| **CompletionOutcomeVersion** | YES | Each evaluation / official completion snapshot |
| Completion Evaluation / Eligibility | **Attributes** on CompletionOutcomeVersion (`evaluation_status`, `eligibility_status`, timestamps, calc/policy pins) — not separate entities unless query isolation forces later |
| **RequirementEvaluation** | YES | Per-requirement outcome under a version |

### 3.4 Graduation layer

| Logical entity | Keep separate? | Reason |
|----------------|----------------|--------|
| **GraduationApproval** | YES | Human decision record (request/decide) linked to a CompletionOutcomeVersion |
| **GraduationAward** | YES | Official award identity |
| **GraduationAwardVersion** / lineage | Prefer **Award + supersession links** (award_version monotonic or new Award row with supersedes) — recommend **GraduationAward** stable + **GraduationAwardVersion** for immutability parity with Completion |

### 3.5 Lineage / governance

| Logical entity | Keep separate? | Reason |
|----------------|----------------|--------|
| **OutcomeSupersession** | YES (or typed links on versions) | predecessor/successor, reason_ref, actor_ref |
| **RevocationRecord** | YES | Authorized human revoke preserving original |
| AuthorizationRole | **NOT created** — actor_ref / authority_ref placeholders only (HD-31) |

### 3.6 Projections / consumers (not owned SSOT)

| Concept | Logical treatment |
|---------|-------------------|
| StudentStatus::Graduated | Projection via future event/sync — **no graduation SSOT table** |
| Transcript | Consumer of Award/Completion pins — reuse 3C.6 model; optional FK from transcript content to award version |
| Publication | Future PublicationIntent linked to AwardVersion — HD-38 open; slot only |

---

## 4. Identity & Scope (HD-39)

### CompletionOutcome natural grain

```text
(school_id, enrollment_id)
```

Optional discriminator only if one enrollment can host multiple independent completion scopes (rare): `completion_scope_code` — **default: one CompletionOutcome per enrollment**.

| Component | Why in grain | Why insufficient alone |
|-----------|--------------|------------------------|
| `school_id` | Tenant boundary; RLS fail-closed | Same student across schools |
| `enrollment_id` | Academic context (year, class, specialization) | Student may have many enrollments |
| `student_id` | Denorm for query only | **Not** identity (HD-39) |
| `specialization_id` / program context | Denorm from enrollment at version time | Not alone — enrollment is authority |
| `academic_year_id` | Denorm from enrollment | Not alone |

**Multiple outcomes:** Yes — one per enrollment (and thus concurrent programs via multiple enrollments).  
**Historical coexistence:** Yes — many CompletionOutcomeVersion / AwardVersion with supersession.  
**Concurrent programs:** Supported via multiple enrollments each with own outcomes.

### GraduationAward natural grain

```text
(school_id, enrollment_id)
```

linked to the CompletionOutcome that was approved. One **current** official award per enrollment typical; historical awards retained via versions/supersession.

---

## 5. School Tenancy Choice

**Decision:** Store **`school_id` explicitly** on CompletionOutcome, CompletionOutcomeVersion, GraduationApproval, GraduationAward, GraduationAwardVersion, EligibilityPolicy (school-scoped policies), aligned with LIVE grades pattern (denorm for RLS).

| Approach | Verdict |
|----------|---------|
| Derive school only via enrollment join | Rejected as sole approach — RLS/fail-closed harder |
| Duplicate school everywhere blindly | Avoid on pure children if parent already scoped — EvidenceItem inherits via version |
| Explicit school on tenant roots + composite consistency with enrollment | **Chosen** |

**PHYSICAL DESIGN REQUIREMENT (future):** composite FK `(enrollment_id, school_id)` → enrollments; ENABLE+FORCE RLS.

---

## 6. Mutability Classification

| Entity | Class |
|--------|-------|
| EligibilityPolicyVersion (published) | Immutable official catalog |
| RequirementDefinitionVersion (published) | Immutable |
| CompletionOutcome | Mutable metadata only (pointer to current version); identity stable |
| CompletionOutcomeVersion (working/candidate) | Replaceable by newer candidate |
| CompletionOutcomeVersion (official) | **Immutable**; supersede |
| RequirementEvaluation / Evidence* under official version | **Immutable** with parent |
| GraduationApproval (decided) | **Immutable** decision facts |
| GraduationAwardVersion (issued) | **Immutable** |
| RevocationRecord / Supersession | Append-only |
| StudentStatus | Projection (external) |

---

## 7. Correction & Revocation (logical)

```text
Official CompletionOutcomeVersion V1 / AwardVersion A1
  → upstream change (grades/results/GPA if pinned)
  → impact detection (outbox)
  → new CompletionOutcomeVersion candidate
  → human review (HD-31 path)
  → optional GraduationApproval + new AwardVersion
  → Supersession links; V1/A1 retained
```

Revocation: **RevocationRecord** references AwardVersion; status becomes REVOKED; no DELETE/overwrite.

---

## 8. Policy / Unit / GPA

- Evaluations pin: `eligibility_policy_version_id`, requirement version ids used, `calculation_version`, evidence fingerprint.  
- Unit types: extensible code on RequirementDefinitionVersion (`unit_kind`) — no seeded kinds as policy.  
- GPA: optional EvidenceItem `source_type=GPA_VERSION` only if future policy requires — **no mandatory GPA attribute**.

---

## 9. Approval vs Award

```text
CompletionOutcomeVersion (eligible)
  → GraduationApproval (requested → approved/rejected)
  → GraduationAwardVersion (issued)
```

Machine evaluation ≠ approval. Actor refs without invented roles.

---

## 10. Temporal Semantics (logical)

| Time | Meaning |
|------|---------|
| `evaluated_at` | When completion version calculated |
| `eligibility_determined_at` | When eligibility status set on version |
| `approval_requested_at` / `decided_at` | Approval workflow |
| `awarded_at` | Official award issuance |
| `superseded_at` / `revoked_at` | Lineage events |
| `published_at` | Future publication (HD-38) |
| Policy `effective_from`/`effective_to` | Policy catalog effectiveness |

Do not conflate decision time with academic effective calendar dates (HD-33/34 still open for semantic meaning of graduation_date).

---

## 11. CQRS (conceptual only)

**Commands:** EvaluateCompletion, RecalculateCompletion, RequestGraduationApproval, ApproveGraduation, RejectGraduation, IssueGraduationAward, SupersedeGraduationOutcome, RevokeGraduationAward, PublishGraduationOutcome (HD-38)  

**Queries:** GetCompletionStatus, GetRequirementEvaluations, GetGraduationApproval, GetGraduationAward, GetOutcomeHistory, GetProvenance  

No handlers.

---

## 12. Outbox / Events

```text
PROPOSED — REQUIRES EVENT GOVERNANCE APPROVAL:
completion.evaluated
completion.eligibility_determined
graduation.approval_requested
graduation.approval_decided
graduation.award_issued
graduation.outcome_superseded
graduation.award_revoked
graduation.publication_requested / published
```

Align naming with existing grade/result outbox conventions at governance time. Fingerprint ≠ idempotency key.

---

## 13. Normalization Notes

- EvidenceItem avoids repeating groups via 1:N.  
- Do not store full grade rows — only typed refs.  
- Denorm `student_id`, `academic_year_id`, `specialization_id` on outcome roots = **controlled denorm** for RLS/query — mark **PHYSICAL DESIGN REQUIREMENT** to enforce consistency with enrollment.  
- No multi-valued JSON as sole SSOT for requirements when countable evaluations needed — prefer RequirementEvaluation rows.

---

## 14. Security / RLS Logical Contract

| Class | Examples |
|-------|----------|
| Direct school-owned | Policy, CompletionOutcome, Award |
| Indirect | EvidenceItem via parent version |
| Forbidden | Cross-school enrollment↔outcome links |

```text
SchoolContext → fail closed → future RLS/FORCE RLS
```

No SQL in this phase.

---

## 15. Reuse vs New

| Existing | Action |
|----------|--------|
| enrollment.enrollments | Reuse as scope SSOT |
| exams.student_grades / Results versions / GPA versions | Evidence sources only |
| security.users | Actor ref target |
| audit/outbox platform | Reuse infrastructure |
| blueprint graduation.* | **STALE** — do not implement as-is |
| StudentStatus | Projection consumer |

---

## 16. Physical Design Deferred

```text
PHYSICAL DESIGN REQUIREMENT (examples — not decided):
- PostgreSQL types, PK strategy, indexes, partitioning
- RLS policy SQL
- Exact table names
- Constraint DDL
```

---

## 17. Companion Artifacts

Entity Grain Register · Relationship Matrix · Source-of-Truth Matrix · Historical Provenance Model · Policy Inventory · Gate
