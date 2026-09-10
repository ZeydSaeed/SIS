# PHASE 3C.11 — SECURITY / RLS IMPLEMENTATION PLAN

**Policies NOT created.**

## Pattern to copy (LIVE Phase 3B)

```sql
ALTER TABLE graduation.<table> ENABLE ROW LEVEL SECURITY;
ALTER TABLE graduation.<table> FORCE ROW LEVEL SECURITY;

CREATE POLICY <table>_school_isolation ON graduation.<table>
USING (
  NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
  AND school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
)
WITH CHECK ( /* same */ );
```

**Rules:** NO RLS DISABLE in normal ops · NO fail-open · NO cross-school access.

---

## Per-table RLS matrix

| Table | RLS | FORCE | Tenant | SELECT | INSERT | UPDATE | DELETE |
|-------|-----|-------|--------|--------|--------|--------|--------|
| eligibility_policies | Y | Y | school_id | school | school | school | DENY prefer |
| eligibility_policy_versions | Y | Y | school_id | school | school | draft only* | DENY published |
| requirement_definitions | Y | Y | school_id | school | school | metadata | DENY if vers |
| requirement_definition_versions | Y | Y | school_id | school | school | draft* | DENY pub |
| completion_outcomes | Y | Y | school_id | school | school | pointer | DENY |
| completion_outcome_versions | Y | Y | school_id | school | school | lineage flags* | DENY |
| requirement_evaluations | Y | Y | school_id | school | school | DENY if official | DENY if off. |
| evidence_sets | Y | Y | school_id | school | school | DENY if off. | DENY if off. |
| evidence_items | Y | Y | school_id | school | school | DENY if off. | DENY if off. |
| graduation_approvals | Y | Y | school_id | school | school | decide once* | DENY decided |
| graduation_awards | Y | Y | school_id | school | school | pointer | DENY |
| graduation_award_versions | Y | Y | school_id | school | school | flags* | DENY |
| outcome_supersessions | Y | Y | school_id | school | school | DENY | DENY |
| revocation_records | Y | Y | school_id | school | school | DENY | DENY |

\*Column-level UPDATE limits enforced primarily by **triggers** + privileges; RLS still school-scopes rows.

## Cross-school FK protection

Composite `(enrollment_id, school_id)` + RLS. Document PG owner-bypass caveat: verify privileged roles cannot insert cross-tenant pairs; app role must not be table owner bypassing RLS unexpectedly.

## Authorization (application)

Laravel Policies / permissions — **separate** from RLS. Hiding UI ≠ security. Role catalogs = **BUSINESS POLICY DEPENDENCY** (HD-31).

## Test cases (future)

- same school read/insert OK  
- cross-school read/insert denied  
- missing GUC fail-closed denied  
- UPDATE/DELETE official denied (trigger+RLS)  
- FORCE prevents owner-table surprises for app role  

## Forbidden

```text
NO RLS DISABLE
NO FAIL-OPEN POLICY
NO CROSS-SCHOOL ACCESS
```
