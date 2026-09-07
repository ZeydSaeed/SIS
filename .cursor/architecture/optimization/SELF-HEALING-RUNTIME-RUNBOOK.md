# Self-Healing Runtime Runbook

## Overview

The performance self-healing layer runs via Laravel scheduler + queue. **SSOT:** `app/Optimization/SelfHealing/` (not `app/Intelligence/SelfHealing/` ops playbooks).

## Components

| Component | Role |
|-----------|------|
| `RunSelfHealingCycleJob` | Scheduled every 5 minutes |
| `SelfHealingPerformanceEngine` | Orchestrates full safety pipeline |
| `optimization:worker` | Dedicated loop with lock TTL |
| `optimization:status` | Runtime status dashboard |
| `optimization:health` | Live telemetry + anomalies |

## Scheduler

Verify in `routes/console.php` or `bootstrap/app.php`:

```bash
php artisan schedule:list | findstr self-healing
```

Expected: `RunSelfHealingCycleJob` every five minutes.

## Queue Worker

Self-healing jobs require a running worker:

```bash
php artisan queue:work --queue=default --timeout=120
```

Or use `optimization:worker` for a dedicated optimization loop.

## Health Checks

```bash
php artisan optimization:status
php artisan optimization:health
php artisan optimization:baseline
php artisan optimization:history
```

### `optimization:status` fields

| Field | Meaning |
|-------|---------|
| `mode` | Effective mode (circuit breaker forces observe) |
| `configured_mode` | `OPTIMIZATION_MODE` env value |
| `circuit_breaker_open` | Safe mode active |
| `baseline_stale` | Recalibration in progress — no aggressive optimization |
| `worker_lock_active` | Another cycle running |
| `cooldowns` | Per-target cooldown timestamps |
| `last_cycle_outcome` | monitor / baseline_warmup / heal_applied / etc. |

## Locks

| Lock | TTL | Purpose |
|------|-----|---------|
| `optimization:self-healing:worker` | 600s (config) | One cycle at a time |
| `optimization:target-lock:{hash}` | 600s | One optimization per target |

If worker crashes, lock expires after TTL — system remains safe (no partial DB writes for ANALYZE beyond event log).

## Failure Recovery

1. **Circuit breaker open** — fix root cause, then `php artisan optimization:rollback --reset-circuit` (if command exists) or clear safe mode via state store admin procedure
2. **Baseline stale** — allow warmup cycles; do not force autonomous mode
3. **Stabilization rollback** — automatic; check `optimization:history`

## Logs

Structured events prefixed `SELF_HEALING:` in application log:

- `SELF_HEALING_CYCLE_STARTED`
- `ANOMALY_DETECTED`
- `RCA_COMPLETED`
- `CHECKPOINT_CREATED`
- `OPTIMIZATION_STARTED` / `OPTIMIZATION_COMPLETED` / `OPTIMIZATION_REJECTED`
- `STABILIZATION_STARTED` / `STABILIZATION_FAILED`
- `OPTIMIZATION_ACCEPTED`
- `CIRCUIT_BREAKER_OPENED` (via safe_mode state)

## Monitoring Alerts (recommended)

| Alert | Condition |
|-------|-----------|
| Safe mode | `circuit_breaker_open = true` |
| Failed cycles | `last_cycle_outcome = error_contained` |
| Stale baseline > 24h | `baseline_stale = true` |
| No cycle > 15 min | `last_cycle_at` older than threshold |

## Production Checklist

- [ ] `OPTIMIZATION_MODE=observe` until Phase 2 gates approved
- [ ] Scheduler cron active (`* * * * * php artisan schedule:run`)
- [ ] Queue worker supervised (systemd/supervisor)
- [ ] Log aggregation for `SELF_HEALING:*` events
- [ ] Alert on circuit breaker / error_contained

## Unified Safety Pipeline

When `OPTIMIZATION_UNIFIED_SAFETY_PIPELINE=true` (default), `DatabaseGuardian::runGrowthAndOptimizationCycle()` does **not** auto-execute recommendations. All autonomous actions must flow through `SelfHealingPerformanceEngine`.
