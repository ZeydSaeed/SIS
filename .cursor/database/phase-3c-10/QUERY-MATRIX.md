# PHASE 3C.10 — QUERY MATRIX

Conceptual only — not executed.

| # | Question | Primary access | Index / strategy |
|---|----------|----------------|------------------|
| 1 | Is enrollment complete? | completion_outcomes → current official version eligibility_status | uq_co_school_enroll + uq_cov_current_official |
| 2 | Why incomplete? | requirement_evaluations WHERE result_status ≠ satisfied | idx_re_version |
| 3 | Which requirements failed? | same + join requirement_definition_versions | idx_re_version INCLUDE req |
| 4 | Supporting evidence? | evidence_sets → evidence_items | idx_ei_set_source |
| 5 | Policy version applied? | completion_outcome_versions.eligibility_policy_version_id | idx_cov_policy |
| 6 | Completion at historical date? | versions WHERE evaluated_at/created_at ≤ T ORDER BY version_no | uq_cov_outcome_ver + time filter |
| 7 | Who approved? | graduation_approvals by version | idx_approval_version |
| 8 | Which award issued? | graduation_awards → current issued version | uq_ga + uq_gav_current |
| 9 | Award revoked? | revocation_records / lifecycle revoked | idx_rev_award_ver |
| 10 | Authoritative state at T? | Reconstruct chain as of T (versions + supersessions) | lineage indexes |
| 11 | Full graduation decision | join outcome→evals→evidence→approval→award | multi-index plan |
| 12 | Current valid versions | partial unique current flags | partial UNIQUE |
| 13 | Historical versions | (parent_id, version_no) | UNIQUE version |
| 14 | Rebuild projections | list current issued awards → emit outbox → projection job | idx_gav_current / school scans |

## Historical reconstruction (conceptual SQL sketch)

```sql
-- Reconstruct why enrollment X graduated with status Y on date Z
-- (illustrative; not executed)

SELECT ...
FROM graduation.completion_outcomes co
JOIN graduation.completion_outcome_versions cov
  ON cov.completion_outcome_id = co.id
 AND cov.school_id = co.school_id
LEFT JOIN graduation.requirement_evaluations re
  ON re.completion_outcome_version_id = cov.id
LEFT JOIN graduation.evidence_sets es
  ON es.completion_outcome_version_id = cov.id
LEFT JOIN graduation.evidence_items ei
  ON ei.evidence_set_id = es.id
LEFT JOIN graduation.graduation_approvals ga
  ON ga.completion_outcome_version_id = cov.id
LEFT JOIN graduation.graduation_award_versions gav
  ON gav.completion_outcome_version_id = cov.id
LEFT JOIN graduation.revocation_records rr
  ON rr.graduation_award_version_id = gav.id
LEFT JOIN graduation.outcome_supersessions os
  ON os.predecessor_id = cov.id OR os.successor_id = cov.id
WHERE co.school_id = :school
  AND co.enrollment_id = :enrollment
  AND cov.evaluated_at <= :z
ORDER BY cov.version_no;
```

Resolve upstream grade/result facts via evidence_items.source_* against exams/results SSOTs as of capture metadata — not mutable “current” only.
