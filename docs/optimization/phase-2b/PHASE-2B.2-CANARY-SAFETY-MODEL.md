# Phase 2B.2 — Canary Safety Model

**Date:** 2026-09-07

---

## Canary Authorization (All Required)

1. Autonomous mode explicitly enabled
2. Environment explicitly authorized (`controlled_environments`)
3. Kill switch **not** engaged
4. Rate limits satisfied
5. Operation = ANALYZE only
6. Explicit target allowlist
7. Valid normalized target
8. Valid recommendation + incident
9. Sufficient confidence
10. Compatible baseline
11. Valid data-scale policy
12. Critical telemetry available
13. Circuit closed
14. Cooldown satisfied
15. Target lock available
16. No ambiguous checkpoint blocking retry
17. Checkpoint created before execution
18. SafeAutoExecutor execution
19. Fresh post-execution telemetry
20. CrossMetricGuard approval
21. Stabilization started on success
22. Final outcome classified + audited

Any failure → **NO AUTONOMOUS EXECUTION**

---

## Production Isolation

Production authorization is **not** implied by:

```env
OPTIMIZATION_MODE=autonomous
```

 alone.

Production must appear in `controlled_environments` **and** pass all other gates. Production is **not** in default controlled environments.

Verified: `OperationalSoakTest::production_isolation_blocks_autonomous_even_when_all_other_gates_satisfied`

---

## Kill Switch

| State | Behavior |
|-------|----------|
| `false` / unset | Normal gate evaluation |
| `true` | Immediate BLOCK (`kill_switch_engaged`) |
| Invalid value | Fail closed (engaged) |

Learning cannot modify kill switch state.

---

## Rate Limiting

| Violation | Code |
|-----------|------|
| Per-cycle exceeded | `rate_limit_cycle` |
| Per-target window exceeded | `rate_limit_target` |
| Global window exceeded | `rate_limit_global` |

Cooldown remains independent — `cooldown_active` blocks same target regardless of incident/recommendation ID.

---

## Recovery Semantics (S21 Unchanged)

| Crash point | Outcome |
|-------------|---------|
| Before checkpoint | Retry allowed if no blocking state |
| After checkpoint started (no execution) | `INCOMPLETE_NO_AUTO_RETRY` → blocks retry |
| After real ANALYZE, before finalize | `EXECUTED_BUT_NOT_FINALIZED` → blocks retry |

No blind retry. No false exactly-once claims.

---

## ANALYZE Rollback

Non-reversible operation. Checkpoint records `rollback_supported: false`, `restore_strategy: non_reversible_safe`.

---

## Production Default

```env
OPTIMIZATION_MODE=observe
```

Unchanged. Production autonomous optimization **NOT AUTHORIZED**.
