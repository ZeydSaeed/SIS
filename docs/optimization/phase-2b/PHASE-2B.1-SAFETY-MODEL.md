# Phase 2B.1 — Safety Model

**Date:** 2026-09-07

---

## Fail-Closed Principle

Any missing, invalid, or unknown mandatory condition → **NO AUTONOMOUS EXECUTION**.

Uncertainty is never converted into permission.

---

## Mandatory Gates (AutonomousExecutionPolicy)

| # | Gate | Denial code |
|---|------|-------------|
| 1 | Mode = autonomous (effective) | `mode_not_autonomous` |
| 2 | Controlled environment | `environment_unauthorized` |
| 3 | Operation = analyze in candidate actions | `operation_not_allowed` |
| 4 | Allowlist configured (non-empty, no wildcards) | `allowlist_invalid` |
| 5 | Incident target present | `target_missing` |
| 6 | Target on explicit allowlist | `target_not_allowlisted` |
| 7 | Confidence ≥ threshold | `insufficient_confidence` |
| 8 | Circuit breaker closed | `circuit_breaker_open` * |
| 9 | Cooldown satisfied | `cooldown_active` |
| 10 | Target lock available | `target_locked` |
| 11 | Baseline compatible (not stale) | `baseline_incompatible` |
| 12 | Data scale allows autonomous | `data_scale_blocked` |
| 13 | No ambiguous checkpoint blocking retry | `checkpoint_ambiguous` |
| 14 | Critical telemetry available | `critical_telemetry_unknown` |

\* When circuit breaker is open, `effectiveMode()` returns `observe`, so denial surfaces as `mode_not_autonomous` — execution is still blocked.

Additional runtime gates (post-policy): `SafeAutoExecutor`, `CrossMetricGuard`, `StabilizationMonitor`.

---

## Allowlist Rules

- Explicit `schema.table` entries only
- Default config: `[]` (fail-closed)
- Rejected: `*`, wildcards, `;`, `--`, empty entries
- No user-provided SQL enters execution path

---

## Target → SQL Security

```text
schema_name / table_name
  → sanitizeIdentifier() [^[a-z_][a-z0-9_]{0,62}$]
  → isAllowlisted()
  → toAnalyzeSql() → "schema"."table"
  → ANALYZE "schema"."table"
```

---

## Learning Boundaries

Learning may influence:

- Recommendation ranking
- Confidence scoring
- Recommendation ordering

Learning may **not** influence:

- Allowlist membership
- Risk tier authorization
- Operation registry
- Environment permission
- Circuit breaker / target lock / checkpoint policy
- Safety gate bypass

Regression: `RecommendationRankerTest::s16_...`, `ControlledAutonomousAnalyzeTest::a14_...`

---

## Production Safety

| Setting | Value |
|---------|-------|
| `config('optimization.mode')` default | `observe` |
| Production in controlled environments | **No** (unless explicitly env-configured — not done in this phase) |
| New autonomous actions | **None** |

---

## S21 Crash Semantics (Unchanged)

```text
real execution may have occurred
  → checkpoint EXECUTED but not finalized
  → recoverIncomplete() marks EXECUTED_BUT_NOT_FINALIZED
  → blocksAutonomousRetry() = true
  → no blind retry
```
