# Autonomous Optimization Skill

Use when the user asks for performance optimization, bottleneck analysis, baseline capture, or autonomous optimization.

## Read First

1. `.cursor/architecture/optimization/AUTONOMOUS-OPTIMIZATION-PROMPT.md`
2. `.cursor/architecture/optimization/README.md`
3. `.cursor/architecture/PERFORMANCE-BUDGET.md`
4. `.cursor/architecture/DATABASE-ADAPTIVE-GOVERNANCE.md`

If touching application code: also read `application-feature/SKILL.md`.
If touching database: also read `database-change/SKILL.md`.

## Steps

### 1. Confirm mode

```bash
# Check config/optimization.php or .env
OPTIMIZATION_MODE=observe   # default — safe
```

### 2. Observe (Level 0)

```bash
php artisan optimization:observe
```

No production code changes in this phase.

### 3. Recommend (Level 1)

```bash
php artisan optimization:recommend
```

Review `.cursor/architecture/optimization/BOTTLENECK-REPORT.md`.
Present findings with evidence. Wait for approval before implementing.

### 4. Implement ONE change (if approved)

- Smallest isolated fix
- Run `composer test` and `php artisan architecture:validate --fitness`
- Document before/after metrics

### 5. Autonomous (Level 2 — optional)

Only when user explicitly sets `OPTIMIZATION_MODE=autonomous`:

```bash
php artisan optimization:run
```

Delegates to `SafeAutoExecutor` for Tier-1 ANALYZE only.

## Scoring

Use `OptimizationScorer`: (Impact × Confidence) / (Risk × Complexity).

Prioritize: High Impact + High Confidence + Low Risk.

## History

Records stored in `storage/app/optimization/history/`.

## Never

- Optimize without baseline
- Combine unrelated optimizations
- Auto-modify source code in observe/recommend mode
- Bypass architecture or intelligence gates
