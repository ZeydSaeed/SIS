# Phase 2A Safety Model

## Production Default

```text
OPTIMIZATION_MODE=observe   (unchanged)
```

Autonomous execution requires explicit `OPTIMIZATION_MODE=autonomous` — never defaulted in config or deployment.

## Autonomous Action Matrix (Actual)

| Action | Autonomous | Safety Gate | Checkpoint | Rollback | Tested | Production Allowed |
| ------ | ---------: | ----------: | ---------: | -------: | -----: | -----------------: |
| ANALYZE | Yes (tier 1) | Scorer + CrossMetric + unknown-metric block | Full lifecycle | **false** (explicit) | S5,S8,S11,S18 | No (observe default) |
| replica_lag flag | Ops allowlist | OpsSelfHealingSafetyGate | No | TTL expiry | S17 | Ops only |
| connection_saturation flag | Ops allowlist | OpsSelfHealingSafetyGate | No | TTL expiry | S17 | Ops only |
| cache_ttl_adjust, etc. | **No** | N/A | N/A | N/A | Config trimmed | No |

## Hard Rules Enforced

1. No schema/index/config/code auto-mutation
2. Learning ranks recommendations — **cannot** escalate risk tier
3. Unknown critical metrics block autonomous accept (`block_autonomous_on_unknown_critical`)
4. Context-incompatible baseline → stale → warmup → no autonomous action
5. Incomplete checkpoint → recovery recorded → **no auto-retry**
6. `runAutonomous()` remains disabled — incident-driven path only

## Ops vs Optimization

| Control | Optimization | Ops |
|---------|-------------|-----|
| Allowlist | `autonomous_actions: [analyze]` | `ops_self_healing.allowlist` |
| Circuit breaker | `CircuitBreaker` + safe_mode | `OpsSelfHealingSafetyGate` |
| Cooldown | `OptimizationCooldownManager` | Per-playbook cache TTL |
| Audit | `SelfHealingEventLogger` | `OPS_SELF_HEALING:*` logs |
