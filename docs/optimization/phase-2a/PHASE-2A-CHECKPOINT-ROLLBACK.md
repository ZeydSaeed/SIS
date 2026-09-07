# Phase 2A Checkpoint & Rollback

## Checkpoint Lifecycle

```text
CREATED → EXECUTION_STARTED → EXECUTED → AFTER_CAPTURED
    → GUARD_EVALUATED → STABILIZATION_STARTED → ACCEPTED
                              ↘ GUARD_REJECTED → REJECTED
INCOMPLETE (error) → RECOVERY_RECORDED (crash recovery)
```

## Checkpoint Matrix

| Field | Created | Updated | Finalized | Tested |
| ----- | ------: | ------: | --------: | -----: |
| checkpoint_id | ✓ | — | — | S12 |
| operation_id | ✓ | — | — | S12 |
| incident_id | ✓ | — | — | S11 |
| recommendation_id | — | ✓ (execution start) | ✓ | S12 |
| target | ✓ | — | — | S12 |
| action | ✓ | — | — | S12 |
| before_state | ✓ | — | — | S11 |
| after_state | — | ✓ (after capture) | ✓ | S11 |
| rollback_supported | ✓ | ✓ | ✓ | S18 |
| execution_status | ✓ | ✓ each transition | ✓ | S12 |
| guard_status | — | ✓ | ✓ | S5 |
| stabilization_status | — | ✓ | ✓ | S11 |
| rollback_status | — | ✓ on reject | ✓ | S10 |
| final_outcome | — | ✓ terminal | ✓ | S10 |

## Crash Recovery

`CheckpointRecoveryService::recoverIncomplete()` at cycle start:

- Finds statuses: EXECUTION_STARTED, EXECUTED, AFTER_CAPTURED, GUARD_EVALUATED, INCOMPLETE
- Transitions to RECOVERY_RECORDED with `INCOMPLETE_NO_AUTO_RETRY`
- Emits `CHECKPOINT_RECOVERY_RECORDED`
- **Does not** auto-retry execution

## Rollback Abstraction

```text
RollbackManager
  └── RollbackStrategy (interface)
        └── NonReversibleRollbackStrategy (analyze)
```

- `supports()`, `isRollbackSupported()`, `rollback()`, `verify()`
- ANALYZE: `rollback_supported=false` — no fake restoration
