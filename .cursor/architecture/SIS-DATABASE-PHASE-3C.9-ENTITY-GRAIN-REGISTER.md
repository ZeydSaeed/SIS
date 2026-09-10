# SIS DATABASE — PHASE 3C.9  
# ENTITY GRAIN REGISTER

**Document type:** LOGICAL ONLY  
**Date:** 2026-09-10  

```text
Technical Identity Candidate = future surrogate — not physical DDL.
```

---

| Entity | Business Meaning | Grain | Natural Identity | Technical Identity Candidate | Owner | School Scope | Mutable? | Versioned? | Official? | Projection? | Source of Truth? | Historical Requirement |
|--------|------------------|-------|------------------|------------------------------|-------|--------------|----------|------------|-----------|--------------|------------------|------------------------|
| EligibilityPolicy | Policy family for completion eligibility | One per school + policy_code | school + policy_code | surrogate | Lifecycle | Explicit school_id | Metadata | Via versions | Catalog | No | Policy family yes | Catalog history via versions |
| EligibilityPolicyVersion | Immutable published policy snapshot | Policy + version_no | policy_id + version_no | surrogate | Lifecycle | From policy | No when published | Yes | When published/effective | No | Policy content pin | Retain all published |
| RequirementDefinition | Requirement under a policy | Policy + requirement_code | policy_id + code | surrogate | Lifecycle | From policy | Metadata | Via versions | Definition | No | Definition family | Via versions |
| RequirementDefinitionVersion | Versioned requirement/unit rule shell | Definition + version_no | def_id + version_no | surrogate | Lifecycle | From policy | No when published | Yes | When published | No | Rule pin (values later) | Retain published |
| CompletionOutcome | Stable completion identity for an enrollment | school + enrollment | school_id + enrollment_id | surrogate | Lifecycle | Explicit | Pointers only | Via versions | Identity | No | Outcome identity | Stable forever |
| CompletionOutcomeVersion | One evaluation/official completion snapshot | Outcome + version_no | outcome_id + version_no | surrogate | Lifecycle | Explicit | No if official | Yes | When finalized official | No | Derived completion state | Retain official + candidates per retention |
| RequirementEvaluation | Result of one requirement for a version | Version + requirement_def_version | version_id + req_ver_id | surrogate | Lifecycle | Via parent | No if parent official | Frozen with parent | Part of official | No | Derived per requirement | With parent version |
| EvidenceSet | Bundle of evidence for a version | 1:1 version | version_id | surrogate or embed | Lifecycle | Via parent | No if official | With version | Part of official | No | Set membership | With parent |
| EvidenceItem | One typed upstream evidence ref | Set + sequence/source key | set_id + source_type + source_ref | surrogate | Lifecycle | Via parent | No if official | With version | Part of official | No | Reference only — not grade SSOT | With parent |
| GraduationApproval | Human approval decision for a completion version | CompletionVersion + approval attempt | completion_version_id + attempt | surrogate | Lifecycle | Explicit | No once decided | Attempt lineage | Decision record | No | Approval decision | Retain decided |
| GraduationAward | Stable award identity for enrollment | school + enrollment | school_id + enrollment_id | surrogate | Lifecycle | Explicit | Pointers | Via versions | Identity | No | Award identity | Stable |
| GraduationAwardVersion | Issued award snapshot | Award + version_no | award_id + version_no | surrogate | Lifecycle | Explicit | No if issued | Yes | When issued | No | Official award facts | Retain issued/superseded/revoked |
| OutcomeSupersession | Lineage edge | predecessor → successor | pair of version refs + type | surrogate | Lifecycle | Via endpoints | Append-only | N/A | Audit | No | Lineage | Retain |
| RevocationRecord | Authorized revoke of an award version | AwardVersion + revoke event | award_version_id + event | surrogate | Lifecycle | Explicit | Append-only | N/A | Event | No | Revoke event | Retain |
| StudentStatus sync | Projection of award | student | student_id (external) | existing students | Students module | Via student/school | Yes (projection) | N/A | No | **Yes** | **No** — projection | External |
| Transcript link | Consumer pin | per 3C.6 | transcript version | existing/future | Results/Transcript | School | Per 3C.6 | Per 3C.6 | Issued artifact | Hybrid | Transcript SSOT is artifact not graduation | Per 3C.6 |

---

## Entities deliberately not created

| Noun | Treatment |
|------|-----------|
| CompletionEligibility entity | Status fields on CompletionOutcomeVersion |
| CalculationVersion registry | Reference string/id pin until shared Results calc catalog reused |
| Role / Committee | authority_ref opaque — HD-31 |
| Honors / Classification tables | Policy-dependent — HD-32 open content |
| Publication entity | Slot until HD-38 |
| GPA threshold table | Forbidden as default — HD-22 |
