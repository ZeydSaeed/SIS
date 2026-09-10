# 09 — Migration Strategy

**Status:** Phase 1 design  
**Related:** ADR-020 mandatory conditions; database-change skill

---

## Hard rules

1. **Never rewrite** applied migration history.  
2. Structural changes = **new** migrations only.  
3. No DROP TABLE/COLUMN/FK, TRUNCATE, RLS disable, or destructive CASCADE on critical SIS data without explicit human approval.  
4. Prefer additive, reversible-where-safe, deterministic migrations.  
5. PostgreSQL-specific features (schemas, RLS, partitions, MVs, advanced indexes) use raw SQL in migrations as needed.  
6. Same change updates `database-blueprint.md` (and indexing/partition docs when relevant).  
7. Laravel app compatibility must be preserved (D2 / condition 9).

---

## Naming

```text
YYYY_MM_DD_HHMMSS_{action}_{schema}_{table}.php
```

---

## Phase workflow

```text
Plan → Impact analysis → Migration → migrate
  → verify (sis:verify-database / tests)
  → FK/index/RLS validation
  → update docs/blueprint
  → Gate Report → STOP → human approval → next phase
```

---

## Rollback

- Prefer `down()` that is safe (drop only objects created in `up()`).  
- If `down()` would destroy academic history, mark irreversible and require restore-from-backup procedure instead of running down in production.  
- Phase 1: N/A (no DDL).

---

## Seeds

Reference/status seeds only unless explicitly requested. No fake production finance/students in shared environments without approval.
