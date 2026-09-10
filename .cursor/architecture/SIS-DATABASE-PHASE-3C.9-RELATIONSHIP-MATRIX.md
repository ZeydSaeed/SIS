# SIS DATABASE — PHASE 3C.9  
# RELATIONSHIP MATRIX

**Document type:** LOGICAL ONLY  
**Date:** 2026-09-10  

---

| Parent | Child | Cardinality | Optionality | Ownership | Identity impact | School boundary | Historical impact | Delete semantics |
|--------|-------|-------------|-------------|-----------|-------------------|-----------------|-------------------|------------------|
| organization.schools | EligibilityPolicy | 1:N | Optional policies | Parent school owns | school in policy identity | Direct | Catalog | Restrict — no cascade erase history |
| EligibilityPolicy | EligibilityPolicyVersion | 1:N | ≥0 versions | Policy owns | version_no monotonic | Inherited | All published retained | No hard delete published |
| EligibilityPolicy | RequirementDefinition | 1:N | ≥0 | Policy owns | requirement_code unique per policy | Inherited | Via versions | Restrict if versions exist |
| RequirementDefinition | RequirementDefinitionVersion | 1:N | ≥0 | Definition owns | version_no | Inherited | Retain published | No hard delete published |
| enrollment.enrollments | CompletionOutcome | 1:1 (default) | Outcome optional until first eval | Enrollment scopes | enrollment in outcome grain | Composite school+enrollment | Multiple version history under one outcome | Restrict enrollment delete if outcomes exist |
| CompletionOutcome | CompletionOutcomeVersion | 1:N | ≥0 | Outcome owns | version_no | Explicit school | Official immutable | No hard delete official |
| CompletionOutcomeVersion | RequirementEvaluation | 1:N | ≥0 | Version owns | per req version | Via parent | Frozen with official | Cascade logical with version only if never official — else restrict |
| CompletionOutcomeVersion | EvidenceSet | 1:1 | Required for official | Version owns | — | Via parent | Frozen | With version |
| EvidenceSet | EvidenceItem | 1:N | ≥0 (empty ≠ satisfied) | Set owns | typed source key | Via parent | Frozen | With set |
| EligibilityPolicyVersion | CompletionOutcomeVersion | 1:N | Required pin when evaluated | Reference | policy pin | Same school | Reproducibility | Restrict policy version delete |
| RequirementDefinitionVersion | RequirementEvaluation | 1:N | Required for that eval row | Reference | — | Same school | Reproducibility | Restrict |
| CompletionOutcomeVersion | GraduationApproval | 1:N | Optional until requested | Approval references version | attempt | Explicit school | Retain decisions | No delete decided |
| GraduationApproval (approved) | GraduationAwardVersion | 1:N | Award after approve | Award references approval | — | Explicit | Lineage | Restrict |
| enrollment.enrollments | GraduationAward | 1:1 (default) | Optional | Enrollment scopes | enrollment in award grain | Composite | Version history | Restrict |
| GraduationAward | GraduationAwardVersion | 1:N | ≥0 | Award owns | version_no | Explicit | Issued immutable | No hard delete issued |
| CompletionOutcomeVersion | GraduationAwardVersion | N:1 or 1:N | Award based on a completion version | Reference | completion pin | Same school | Provenance | Restrict |
| *Version (official) | OutcomeSupersession | 1:N | Optional | Lineage | pred/succ | Via ends | Required for supersede | Append-only |
| GraduationAwardVersion | RevocationRecord | 1:N | Optional | Revoke event | — | Explicit | Required if revoked | Append-only |
| students.students | CompletionOutcome | 1:N | Via enrollment | Denorm student_id | **not** identity | Via school | — | Restrict |
| GraduationAwardVersion | StudentStatus (external) | 1:N conceptual | Projection | Event sync | — | School | Not historical SSOT | N/A |
| GraduationAwardVersion | TranscriptVersion (3C.6) | N:M conceptual | Optional pin | Consumer | — | School | Transcript own lineage | No ownership from graduation |

### N:M note

Graduation↔Transcript is **logical many-to-many via pins/content items**, not a graduation-owned junction SSOT. Prefer transcript SourceItem → award version (3C.6 style).

### Forbidden relationships

Cross-school CompletionOutcome.enrollment_id → other school enrollment.  
Award without school-consistent completion approval.
