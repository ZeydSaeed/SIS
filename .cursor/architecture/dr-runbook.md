# Disaster Recovery Runbook

> **RPO:** 15 minutes | **RTO:** 1 hour  
> **Scenario:** 45K students, PostgreSQL Primary failure or corruption

## Prerequisites

- [ ] Daily full backup automated (02:00)
- [ ] WAL archiving continuous (`archive_mode = on`)
- [ ] Read replica streaming (if Primary total loss)
- [ ] DR environment documented
- [ ] On-call contact list current

---

## Incident Types

| Type | Severity | First Action |
|------|----------|--------------|
| Primary down | Critical | Failover to replica OR restore |
| Data corruption | Critical | Stop writes, PITR to last good point |
| Replica lag > 5 min | Warning | Investigate, pause report reads |
| Backup failed | Critical | Manual backup immediately |
| Disk full | Critical | Expand disk or archive old partitions |

---

## Runbook A — Primary Database Failure

### Step 1: Detect (0–2 min)

```bash
# Check Primary health
pg_isready -h primary-host -p 5432
# App logs: connection refused / timeout
```

**Owner:** On-call engineer  
**Declare incident** in monitoring channel.

### Step 2: Stop Writes (2–5 min)

```bash
# Enable maintenance mode in Laravel
php artisan down --secret="recovery-token"
# Or load balancer: remove app nodes from pool
```

**Expected:** No new writes to failed Primary.

### Step 3: Assess (5–10 min)

| Question | Action |
|----------|--------|
| Primary recoverable (restart)? | Try `systemctl restart postgresql` |
| Hardware/disk failure? | Proceed to failover/restore |
| Data corruption? | Runbook B (PITR) |

### Step 4: Failover to Replica (if configured)

```bash
# On replica — promote to primary
pg_ctl promote -D /var/lib/postgresql/data

# Update PgBouncer / app .env
DB_HOST=replica-host-new-primary
```

**Verify:**

```sql
SELECT pg_is_in_recovery();  -- must return false
SELECT count(*) FROM enrollment.enrollments;  -- sanity check
```

### Step 5: Reconnect Application (10–20 min)

```bash
php artisan config:clear
php artisan up
# Smoke test: login, search student, read dashboard
```

### Step 6: Integrity Checks (20–40 min)

```sql
-- Orphan check sample
SELECT count(*) FROM enrollment.enrollments e
LEFT JOIN students.students s ON s.id = e.student_id
WHERE s.id IS NULL;  -- must be 0

-- Latest attendance date
SELECT max(attendance_date) FROM attendance.records;
```

### Step 7: Resume & Document (40–60 min)

- [ ] Service restored
- [ ] Incident timeline logged
- [ ] Schedule new replica from promoted primary
- [ ] Root cause analysis within 48h

**Target RTO:** 1 hour

---

## Runbook B — Point-in-Time Recovery (Corruption)

### When to Use

```
Error discovered at 14:32
Need state at 14:31 (before bad migration/query)
```

### Step 1: Stop All Writes

```bash
php artisan down
# Revoke app DB write role if needed
```

### Step 2: Identify Recovery Target

```
Recovery target time: 2026-09-05 14:31:00 +03
```

### Step 3: Restore Base Backup

```bash
# Stop PostgreSQL on recovery server
systemctl stop postgresql

# Restore latest base backup
rm -rf /var/lib/postgresql/data/*
tar -xzf /backup/base/latest.tar.gz -C /var/lib/postgresql/data/

# Configure recovery
cat >> /var/lib/postgresql/data/postgresql.conf <<EOF
restore_command = 'cp /backup/wal/%f %p'
recovery_target_time = '2026-09-05 14:31:00+03'
recovery_target_action = 'promote'
EOF

touch /var/lib/postgresql/data/recovery.signal
systemctl start postgresql
```

### Step 4: Verify Recovery Point

```sql
SELECT now();  -- confirm DB stopped at target
-- Run integrity queries
-- Compare row counts with expected
```

### Step 5: Switch Application

Update `DB_HOST` to recovered instance → smoke tests → `php artisan up`.

### Step 6: Post-Recovery

- [ ] Identify cause (bad migration? manual SQL?)
- [ ] Fix forward in new migration
- [ ] Re-establish replica
- [ ] Document in incident log

**Target RPO:** 15 minutes (WAL granularity)

---

## Runbook C — Backup Verification (Monthly Drill)

```bash
# 1. Restore to isolated staging server
# 2. Run migrations check: php artisan migrate:status
# 3. Sample queries: student count, latest enrollment
# 4. Record in load-test-results.md or ops log
# 5. Time the restore — must be < RTO
```

---

## Rollback (If Recovery Wrong)

1. Stop application again
2. Do not write to wrong-recovery DB
3. Re-run PITR with corrected target time
4. Escalate to DBA

---

## Contacts & Escalation

| Role | Responsibility |
|------|----------------|
| On-call Engineer | Steps 1–5 |
| Tech Lead | Failover decision |
| DBA | PITR execution |
| Product Owner | User communication |

---

## Validation Queries Post-Recovery

```sql
SELECT count(*) FROM students.students;
SELECT count(*) FROM enrollment.enrollments WHERE status = 1;
SELECT count(DISTINCT school_id) FROM organization.schools;
SELECT max(created_at) FROM audit.audit_logs;
```

Expected for 45K scenario: ~45,000 active enrollments, 20 schools.

---

## Related

- [postgresql-tuning.md](./postgresql-tuning.md) — backup config
- [production-readiness.md](./production-readiness.md) — pre-launch checklist
- [security-audit-resilience.md](./security-audit-resilience.md)
