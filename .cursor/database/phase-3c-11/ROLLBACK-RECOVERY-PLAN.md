# PHASE 3C.11 — ROLLBACK & RECOVERY PLAN

## Critical distinction

| Concept | Meaning |
|---------|---------|
| Deployment rollback | Undo release / empty DDL |
| Data correction | New version + supersession |
| Business revocation | Append revocation_records |
| Version supersession | HD-35 lineage |

**These are NOT equivalent.** Never “roll back” official academic truth by DELETE.

---

## Per unit

| Unit | Preflight | Forward failure | Migration rollback | App rollback | Irreversible? |
|------|-----------|-----------------|--------------------|--------------|---------------|
| M01 schema | SchemaHelper list | ignore | no-op preferred | n/a | low |
| M02–M15 empty tables | FK prereqs | stop | DROP TABLE IF EMPTY + approval | n/a | low if empty |
| M16 indexes | — | drop index | DROP INDEX | n/a | low |
| M17 RLS | SchoolContext ready | **do not fail-open** | DROP POLICY only with approval; DISABLE flagged dangerous | feature flag off | security-sensitive |
| M18 triggers | — | DROP TRIGGER | DROP TRIGGER | n/a | low |
| App feature | DB green | revert deploy | n/a | previous release | low |
| Official data written | backups | compensate via supersede/revoke | **no DELETE rollback** | — | **academically irreversible** |

## Outbox implications

After commit, consumers may have run. Rollback of app ≠ delete outbox history. Use compensating events / rebuild.

## Recovery

1. Restore from backup only under DR governance  
2. Prefer supersede/revoke for business errors  
3. Rebuild StudentStatus from award SSOT  
4. Re-drive outbox failures via attempts column  

## Data compatibility

New empty `graduation.*` tables are additive (**expand**). Old app ignoring them remains compatible until feature switch.
