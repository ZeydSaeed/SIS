# Autonomous Optimization / Self-Healing Skill

Use when the user asks for performance optimization, self-healing, adaptive baselines, or continuous monitoring.

## Read First

1. `.cursor/architecture/optimization/SELF-HEALING-PERFORMANCE-PROMPT.md`
2. `.cursor/architecture/optimization/AUTONOMOUS-OPTIMIZATION-PROMPT.md`
3. `.cursor/architecture/optimization/README.md`

## Background Monitoring (24/7)

The system runs automatically:

```text
Scheduler → RunSelfHealingCycleJob (every 5 min)
```

Check status:

```bash
php artisan optimization:status
php artisan optimization:health
```

Manual single cycle:

```bash
php artisan optimization:worker --once
```

## Modes

| Mode | Behavior |
|------|----------|
| `observe` (default) | Monitor + baseline — no changes |
| `recommend` | + RCA reports when degraded |
| `autonomous` | + Tier-1 self-heal with rollback |

## Self-Healing Safety

- Hysteresis before acting
- Adaptive baseline (no poisoning)
- Circuit breaker → safe mode
- Cooldown + target lock
- Stabilization window post-accept
- Failure contained — never crash app

## Commands

```bash
php artisan optimization:status
php artisan optimization:health
php artisan optimization:baseline [--capture]
php artisan optimization:history
php artisan optimization:rollback {id} [--reset-safe-mode]
php artisan optimization:worker [--once]
```

## Never

- Change business behavior for performance
- Skip baseline or root cause evidence
- Bypass circuit breaker or architecture gates
- Auto-modify schema/auth/grades/financial logic
