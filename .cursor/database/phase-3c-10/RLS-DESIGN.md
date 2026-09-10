# PHASE 3C.10 — RLS DESIGN

**Policies NOT created. Design only.**

Align with LIVE Phase 3B pattern: `ENABLE ROW LEVEL SECURITY` + `FORCE ROW LEVEL SECURITY`; GUC `app.current_school_id`; school_id equality.

| Table | RLS | SELECT | INSERT | UPDATE | DELETE | Tenant Source | Notes |
|-------|-----|--------|--------|--------|--------|---------------|-------|
| eligibility_policies | YES | school match | school match | school match | DENY (prefer soft retire) | app.current_school_id | Catalog |
| eligibility_policy_versions | YES | school | school | limited draft only | DENY published | denorm school_id | Published immutable |
| requirement_definitions | YES | school | school | metadata | DENY if versions | school_id | |
| requirement_definition_versions | YES | school | school | draft only | DENY published | school_id | |
| completion_outcomes | YES | school | school | pointer updates only | DENY | school_id | |
| completion_outcome_versions | YES | school | school | flag/lineage only | DENY | school_id | Official rows no score mutate |
| requirement_evaluations | YES | school | school | DENY if official parent | DENY if official | school_id | |
| evidence_sets | YES | school | school | DENY if official | DENY if official | school_id | |
| evidence_items | YES | school | school | DENY if official | DENY if official | school_id | |
| graduation_approvals | YES | school | school | decision once | DENY decided | school_id | |
| graduation_awards | YES | school | school | pointer | DENY | school_id | |
| graduation_award_versions | YES | school | school | lineage/revoke flags | DENY issued payloads | school_id | |
| outcome_supersessions | YES | school | school | DENY | DENY | school_id | Append-only |
| revocation_records | YES | school | school | DENY | DENY | school_id | Append-only |

### Policy model

```text
USING (school_id = current_setting('app.current_school_id', true)::bigint)
WITH CHECK (same)
```

Exact expression MUST match LIVE grades/exams RLS helper conventions (verify in Phase 3C.11 against `StudentGrades` RLS migration).

### Bypass

- Service roles / migrations: existing SIS bypass pattern only (documented security architecture).  
- Never bypass for tenant user sessions.  
- Cross-school admin reporting: read replica + elevated role design — **HUMAN DECISION** if needed; not open RLS hole.

### DELETE

Immutable/official academic tables: **DELETE prohibited** at RLS and privilege level.

### RLS + FK caveats (POSTGRESQL-SPECIFIC)

- FK checks run as table owner / bypass RLS by default for the referenced table in many setups — **must verify** LIVE behavior for composite FKs.  
- Application must still enforce same-school on insert.  
- Composite FK `(enrollment_id, school_id)` prevents cross-school enrollment pairing even if RLS is mis-set on child.  
- Documented implementation caveat: confirm `session_replication_role` / owner bypass does not allow silent cross-tenant inserts via privileged paths.

### Cross-school integrity

Independent FKs to `enrollment_id` alone are **forbidden**. Always composite with `school_id` where parent is school-scoped.
