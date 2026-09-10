# SIS DATABASE — PHASE 3C.9  
# SOURCE-OF-TRUTH MATRIX

**Document type:** LOGICAL ONLY  
**Date:** 2026-09-10  

```text
Graduation/Completion must never become SSOT for upstream academic facts.
```

---

| Fact / Concept | SSOT | Consumer (incl. Graduation) | Derived? | Mutable? | Historical? |
|----------------|------|------------------------------|----------|----------|-------------|
| Grade scores | `exams.student_grades` | EvidenceItem ref | No | Via VOID+INSERT | Yes |
| Term Results | Term Result versions (3C.2) | EvidenceItem ref | Yes | Versioned | Yes |
| Annual Results | Annual Result versions (3C.3) | EvidenceItem ref | Yes | Versioned | Yes |
| GPA | GPA versions (3C.4) | Optional EvidenceItem | Yes | Versioned | Yes |
| Ranking | Ranking snapshots (3C.5) | Not default evidence (DL-021) | Yes | Versioned | Yes |
| Promotion | promotion module (future) | Not graduation SSOT (DL-021) | Yes | Own lifecycle | Yes |
| Enrollment | `enrollment.enrollments` | Outcome scope | No | Status/effective dates | Yes |
| Program / specialization | vocational/curriculum + enrollment.specialization_id | Denorm / context | No | Curriculum governed | Yes |
| School | `organization.schools` | Tenant | No | Org governed | Yes |
| Student | `students.students` | Denorm; StudentStatus projection target | No | Student module | Yes |
| Eligibility policy content | EligibilityPolicyVersion | Evaluation pins | No (catalog) | New versions only | Yes |
| Requirement definitions | RequirementDefinitionVersion | RequirementEvaluation | No (catalog) | New versions only | Yes |
| Evidence membership | EvidenceSet/Item under CompletionOutcomeVersion | Evaluation | Derived selection | Frozen when official | Yes |
| Completion eligibility/result | CompletionOutcomeVersion | Award workflow; transcript | **Yes derived** | Official immutable | Yes |
| Approval decision | GraduationApproval | Award issuance | Decision record | Immutable when decided | Yes |
| Graduation award | GraduationAwardVersion | Transcript; StudentStatus projection; certificates later | **Yes derived official** | Issued immutable | Yes |
| Transcript | Transcript live/issued (3C.6) | Readers | Hybrid | Issued immutable | Yes |
| StudentStatus::Graduated | **Not SSOT** — projection of award | UI/enrollment gates | Projection | Sync mutable | Audit via events |
| Blueprint min_gpa etc. | **Not SSOT** — STALE | None | — | — | Doc only |

---

## Anti-SSOT rules

| Forbidden | Why |
|-----------|-----|
| Store authoritative score on EvidenceItem | Duplicates grades |
| Infer graduation solely from StudentStatus | DL-022 |
| Infer graduation solely from transcript issued | DL-018 / 3C.6 |
| Require GPA column on every CompletionOutcomeVersion | HD-22 |
| Use promotion.records as graduation | DL-021 |
