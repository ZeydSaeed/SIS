# RLS POST-IMPLEMENTATION AUDIT (F-11A-001)

| Table | Created in | RLS same migration | FORCE | Policy | Fail-closed | Verified |
| ----- | ---------- | -----------------: | ----: | -----: | ----------: | -------: |
| eligibility_policies | 170200 | YES | YES | YES | YES | YES |
| eligibility_policy_versions | 170200 | YES | YES | YES | YES | YES |
| requirement_definitions | 170200 | YES | YES | YES | YES | YES |
| requirement_definition_versions | 170200 | YES | YES | YES | YES | YES |
| completion_outcomes | 170300 | YES | YES | YES | YES | YES |
| completion_outcome_versions | 170300 | YES | YES | YES | YES | YES |
| evidence_sets | 170400 | YES | YES | YES | YES | YES |
| evidence_items | 170400 | YES | YES | YES | YES | YES |
| requirement_evaluations | 170400 | YES | YES | YES | YES | YES |
| graduation_approvals | 170500 | YES | YES | YES | YES | YES |
| graduation_awards | 170500 | YES | YES | YES | YES | YES |
| graduation_award_versions | 170500 | YES | YES | YES | YES | YES |
| outcome_supersessions | 170600 | YES | YES | YES | YES | YES |
| revocation_records | 170600 | YES | YES | YES | YES | YES |

M17′ migration asserted policies at migrate time. LIVE re-verify: all FORCE=1.

Mandatory column **NO** count: **0**
