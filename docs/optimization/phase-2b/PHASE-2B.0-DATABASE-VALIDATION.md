# Phase 2B.0 — Database Validation

## Environment (Local Herd)

| Field | Value |
|-------|-------|
| Host | 127.0.0.1 |
| Port | 5432 |
| Database | sis (isolated test table — NOT production data table) |
| Version | PostgreSQL 18.2 |
| User | postgres |
| Isolation | Dedicated table `intelligence.optimization_validation_target` |

## Test Target

```text
Schema: intelligence
Table:  optimization_validation_target
Qualified: intelligence.optimization_validation_target
Rows: 500 (deterministic seed)
```

Migration: `database/migrations/2026_09_07_120000_create_optimization_validation_target_table.php`

## Permissions

Uses application DB user (`postgres` in local dev). Minimum required: `ANALYZE` on target table (via table ownership / schema privileges).

## Execution Evidence

Real ANALYZE proven by:

1. `OptimizationEvent.action_taken` = `ANALYZE intelligence.optimization_validation_target`
2. `OptimizationEvent.evidence_before` / `evidence_after` from `pg_stat_user_tables`
3. `pg_stat_user_tables.last_analyze` timestamp updated after execution

Query used by `SafeAutoExecutor` and `PostgreSqlAnalyzeEvidence`:

```sql
SELECT n_live_tup, last_analyze, last_autoanalyze
FROM pg_stat_user_tables
WHERE schemaname = 'intelligence' AND relname = 'optimization_validation_target'
```

## Allowlist (Test Profile)

```php
'optimization.analyze.allowed_targets' => ['intelligence.optimization_validation_target']
```

Production default: `[]` (fail-closed). Explicit env required:

```env
OPTIMIZATION_ANALYZE_ALLOWED_TARGETS=intelligence.optimization_validation_target
```

When unset or empty, autonomous ANALYZE is blocked at `AnalyzeTargetPolicy` regardless of other gates.
