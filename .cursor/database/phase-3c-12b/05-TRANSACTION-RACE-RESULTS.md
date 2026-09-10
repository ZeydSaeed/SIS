# Phase 3C.12B — Transaction Race Results

## Scenarios

| Scenario | Observed | SQLSTATE |
|----------|----------|----------|
| Dual insert same completion outcome | One commit; one unique_violation | `23505` |
| Dual insert same award identity | One row remains | `23505` |
| Dual `is_current_official=true` versions | One current remains | `23505` |
| Dual same `version_no` | One version remains | `23505` |
| Dual same idempotency PK | One key row | `23505` |
| Parallel process race (outcomes) | Exit 0 + exit 1 | `23505` on loser |
| Deadlock `40P01` | Not observed | — |
| Serialization failure `40001` | Not observed (default READ COMMITTED) | — |

## Rollback / partial state

On loser path, transaction rolls back; no second outcome/award/current-official row remains. No orphan versions observed in these insert-only races.

## Version lineage under race

Concurrent dual-current and duplicate `version_no` are rejected by partial UNIQUE / UNIQUE constraints. Branching `2A/2B` current officials are not possible under the partial unique index.

Supersession-table concurrent graph cycles were **not** stress-tested (no CQRS supersession writers). Catalog FKs + CHECKs remain in place.

## Lock observation (not tuning)

Parallel losers block briefly on unique index until winner commits, then fail with `23505`. No indexes or advisory locks were added in this phase.

## Verdict

```text
TRANSACTION SAFETY: PASS
VERSION LINEAGE: PASS
```

Conditions: lineage concurrent supersession writers deferred with CQRS; insert/current-flag races verified.
