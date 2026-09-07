# Phase 2B.0 — S21 Crash After Execution

## Scenario

```text
1. Real ANALYZE executes via SafeAutoExecutor
2. Checkpoint → EXECUTION_STARTED → EXECUTED
3. Worker crash (simulated — no finalization)
4. CheckpointRecoveryService.recoverIncomplete()
5. Recovery classifies EXECUTED_BUT_NOT_FINALIZED
6. blocksAutonomousRetry(target) = true
```

## Implementation

`CheckpointRecoveryService` distinguishes:

| Prior Status | Final Outcome | Auto Retry |
|--------------|---------------|------------|
| EXECUTION_STARTED | INCOMPLETE_NO_AUTO_RETRY | No |
| EXECUTED / AFTER_CAPTURED / GUARD_EVALUATED | EXECUTED_BUT_NOT_FINALIZED | No |

## Test

**File:** `tests/Feature/Optimization/PostgreSql/RealAnalyzeExecutionTest.php`  
**Method:** `s21_crash_after_real_analyze_marks_ambiguous_and_blocks_duplicate`

## Idempotency Classification

ANALYZE is operationally idempotent (non-destructive) but the executor treats **ambiguous post-execution** states as **non-retry** to prevent duplicate execution under uncertainty.

```text
known NOT executed     → INCOMPLETE_NO_AUTO_RETRY (still no auto-retry per policy)
known executed         → terminal success path
execution unknown      → EXECUTED_BUT_NOT_FINALIZED → block retry
```
