# PHASE 3C.10 — MIGRATION ORDER (PLAN ONLY)

**No migration files created.**

## Dependency sequence

```text
1. Ensure schema `graduation` exists (CREATE SCHEMA IF NOT EXISTS) — design only
2. eligibility_policies
3. eligibility_policy_versions
4. requirement_definitions
5. requirement_definition_versions
6. completion_outcomes
7. completion_outcome_versions
8. evidence_sets
9. evidence_items
10. requirement_evaluations
11. graduation_approvals
12. graduation_awards
13. graduation_award_versions
14. outcome_supersessions
15. revocation_records
16. Indexes (UNIQUE + partial UNIQUE + supporting BTREE)
17. RLS ENABLE + FORCE + policies
18. Immutability triggers + privilege grants
19. Outbox event registration (config/taxonomy — not competing tables)
20. Idempotency key namespaces documented for graduation ops
```

## Prerequisites (existing)

- `organization.schools` (or LIVE school table)  
- Enrollment tables with `(id, school_id)` unique for composite FK  
- `audit.outbox_messages`  
- `audit.idempotency_keys`  
- Phase 3A/3B exams/grades (consumers of evidence refs only)

## Blueprint conflict

Blueprint stale objects `graduation.eligibility_rules` / `graduation.records` — **do not create**. Deprecation note only:

```text
DEPRECATION PLAN: ignore stale blueprint names
COMPATIBILITY WINDOW: N/A (never created)
HUMAN APPROVAL REQUIRED before blueprint rewrite in later phase
```

## Rollback (creation phase)

| Step | Rollback | Irreversible? |
|------|----------|---------------|
| CREATE SCHEMA/TABLE empty | DROP TABLE/SCHEMA | Only if empty — still requires approval |
| Indexes | DROP INDEX | Reversible |
| RLS | DROP POLICY / DISABLE — **forbidden as automatic** | Flag irreversible security change |
| Triggers | DROP TRIGGER | Reversible if empty |
| Data after load | Cannot silent delete official rows | Irreversible academically |

Zero-destructive-change: no DROP of production objects as automatic step.
