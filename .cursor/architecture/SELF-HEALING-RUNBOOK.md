# Self-Healing Runbook

> **Scope:** Safe, limited automated responses to known operational failures.  
> **Not Self-Learning:** These are predefined playbooks with verification steps.  
> **Not Autonomous:** Tier 1 auto-actions only — schema changes never auto-heal.

---

## Self-Healing vs Self-Learning

| Capability | Mechanism | Example |
|------------|-----------|---------|
| **Self-Healing** | Known failure → predefined safe response | Route reads away from lagging replica |
| **Self-Learning** | Historical outcomes → improved future recommendations | Partition change success pattern |
| **Self-Adaptive** | Threshold breach → policy-driven adjustment | Connection pool resize |

---

## Allowed Auto-Actions (Tier 1)

| Scenario | Detection | Auto-Response | Verification | Rollback |
|----------|-----------|---------------|--------------|----------|
| Connection spike | pool utilization > 85% for 5 min | Increase PgBouncer pool size + alert | Connections stabilize < 70% | Revert pool config |
| Replica lag | lag > 30s for 2 min | Stop routing reads to replica | Lag < 10s for 5 min | Re-enable replica reads |
| Cache stampede | Redis miss rate > 80% | Extend TTL temporarily + single-flight lock | Hit rate > 60% | Restore TTL |
| Queue backlog | attendance queue > 10K jobs | Scale worker count | Queue < 1K in 15 min | Scale down workers |
| Disk warning | storage > 85% | Alert + pause non-critical archival jobs | Manual review | Resume jobs |
| Long-running query | query > 5 min blocking vacuum | Alert + terminate if idle in transaction | Vacuum resumes | N/A |

**All Tier 1 actions:** log to audit trail with correlation ID.

---

## Playbook: Connection Spike

```text
Detection
  metric: pgbouncer_pool_utilization > 85%
  duration: 5 minutes

Diagnosis
  check: active connections per app node
  check: morning attendance window (8:00–8:30)?
  check: connection leak in app?

Auto-Response (Tier 1)
  1. Increase pool max_connections by 20% (cap: configured max)
  2. Alert ops channel
  3. Enable request queuing if available

Verification (5 min)
  pool utilization < 70%?
  p95 latency within PERFORMANCE-BUDGET?

Failure
  If still > 85% → escalate to human (possible leak or need horizontal scale)
```

---

## Playbook: Replication Lag

```text
Detection
  metric: replication_lag_seconds > 30
  duration: 2 minutes

Diagnosis
  check: bulk write job running?
  check: primary CPU/IOPS spike?
  check: network between primary and replica?

Auto-Response (Tier 1)
  1. Route all read traffic to primary (app config flag)
  2. Alert DBA
  3. Pause non-critical report jobs using replica

Verification
  lag < 10s for 5 consecutive minutes?

Recovery
  Re-enable replica reads gradually (10% → 50% → 100% over 15 min)

Failure
  lag > 120s → DR runbook evaluation
```

---

## Playbook: Morning Attendance Overload

Cross-reference [peak-hour-strategy.md](./peak-hour-strategy.md).

```text
Detection
  metric: attendance queue depth > 5000 during 8:00–8:30
  metric: attendance write p95 > budget

Auto-Response (Tier 1)
  1. Scale queue workers to peak profile
  2. Enable batch write coalescing window
  3. Defer non-critical MV refresh

Verification
  queue drained by 8:45?
  no data loss (idempotency keys verified)

Human Gate
  If queue > 20000 → manual intervention + comms to schools
```

---

## Playbook: Cache Failure (Redis Down)

```text
Detection
  redis health check fail

Auto-Response (Tier 1)
  1. App falls back to DB (cache miss = DB query)
  2. Alert ops
  3. Do NOT write to Redis as source of truth

Verification
  p95 within degraded budget (define in PERFORMANCE-BUDGET)?
  no stale data served (cache was read-through only)

Recovery
  Redis restored → warm critical caches → monitor hit rate
```

**Rule:** Redis failure must never corrupt academic data.

---

## Forbidden Auto-Healing

| Action | Why forbidden |
|--------|---------------|
| DROP INDEX | May break reports |
| ALTER TABLE | Schema change requires migration |
| Disable RLS | Security breach |
| DELETE rows | Academic data violation |
| Failover primary without DR runbook | Data loss risk |
| Auto-add index in production | Write overhead unknown |

---

## Escalation Matrix

| Severity | Condition | Response time | Owner |
|----------|-----------|---------------|-------|
| S1 | Primary DB unreachable | Immediate — DR runbook | DBA on-call |
| S2 | Replica lag > 120s | 15 min | DBA |
| S3 | P95 breach > 1 hour | 30 min | Dev + DBA |
| S4 | Disk > 90% | 1 hour | Ops |
| S5 | Tier 1 auto-action failed | 15 min | On-call engineer |

---

## Monitoring Requirements (Phase 2)

Before Self-Healing is operational:

- [ ] Prometheus metrics: connections, lag, queue depth, disk, p95
- [ ] Alertmanager rules for each playbook trigger
- [ ] App feature flag: `read_from_replica` (toggle without deploy)
- [ ] Audit log for all Tier 1 auto-actions
- [ ] Runbook tested in staging monthly

---

## Related

- [DATABASE-INTELLIGENCE-LAYER.md](./DATABASE-INTELLIGENCE-LAYER.md)
- [dr-runbook.md](./dr-runbook.md)
- [peak-hour-strategy.md](./peak-hour-strategy.md)
- [PERFORMANCE-BUDGET.md](./PERFORMANCE-BUDGET.md)
- [production-readiness.md](./production-readiness.md)
