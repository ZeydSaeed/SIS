# Security, Audit & Resilience

## Authentication & Authorization

### RBAC Roles

```
Administrator | Teacher | Supervisor | Accountant
Registrar | Principal | Directorate | Ministry
```

### Authorization Layers

```
Layer 1: Application middleware (primary)
Layer 2: Policy classes per model/action
Layer 3: PostgreSQL RLS (where appropriate, not replacement)
```

Application authorization is primary. RLS adds defense-in-depth for multi-tenant isolation.

## Row-Level Security (RLS)

Enable when scope isolation is critical:

```sql
-- Example: school admin sees only own school
ALTER TABLE enrollment.enrollments ENABLE ROW LEVEL SECURITY;

CREATE POLICY school_isolation ON enrollment.enrollments
  USING (school_id = current_setting('app.current_school_id')::BIGINT);
```

**RLS scope examples:**

| Role | Scope |
|------|-------|
| Teacher | Assigned sections/subjects only |
| School Admin | Own school only |
| Directorate | Schools in directorate |
| Ministry | All schools |

**Index columns used in RLS policies:** `school_id`, `teacher_id`, `directorate_id`

## Audit Trail

### `audit.audit_logs` — Required Fields

```
user_id, action, entity_type, entity_id
old_values (JSONB), new_values (JSONB)
ip_address, user_agent, correlation_id, created_at
```

### Rules

- Log all create/update/delete on academic records
- Never store passwords, tokens, or unnecessary PII in audit
- Audit logs are append-only — never deleted
- Partition by `created_at` when volume grows

### Correlation ID

Every request propagates correlation ID through all layers:

```
REQ-2026-000123
  → API middleware
  → Service layer
  → Queue jobs
  → Audit log entries
```

## Document Integrity

Important documents (certificates, transcripts) store SHA-256 hash:

```
file → SHA-256 → stored in file_hash column
```

Verify on download/generation to detect tampering.

## Backup & Point-in-Time Recovery

### Production Requirements (P0)

```
Full Backup (daily, e.g. 02:00)
    +
Continuous WAL archiving
    =
Point-in-Time Recovery (PITR)
```

**Example recovery:**

```
Database corrupted at 14:32
→ Restore to 14:31 using WAL
```

### Backup Checklist

- [ ] Daily full backup automated
- [ ] WAL archiving continuous
- [ ] Backup restoration tested monthly
- [ ] Backup stored off-site
- [ ] Retention policy defined

## Disaster Recovery

| Metric | Example Target | Meaning |
|--------|---------------|---------|
| RPO | 15 minutes | Max acceptable data loss |
| RTO | 1 hour | Max acceptable downtime |

### DR Architecture

```
Primary
  ├── Daily Backup
  ├── WAL Archive
  └── Streaming Replica
          ↓
      DR Environment (separate region/VM)
```

## Monitoring & Alerting

### Monitor

```
CPU, RAM, Disk, IOPS
DB Connections, Locks, Deadlocks
Cache Hit Ratio, Slow Queries
Replication Lag, Database/Table/Index Size
Autovacuum activity, Backup Status
Queue Length, Redis Memory
Application Error Rate
```

### Alert Thresholds

```
CPU > 80%          → Warning
Disk > 80%         → Warning
Connections > 80%  → Warning
Replication lag > threshold → Critical
Backup failed      → Critical
Slow query > 2s    → Investigate
Deadlocks rising   → Investigate
Redis memory > 80% → Warning
```

## Migration Policy

**Never change production database manually.**

```
Write Migration → Code Review → Test (staging) → Backup → Deploy → Verify
```

### Naming Convention

```
V001__create_organization_schema.sql
V002__create_academic_schema.sql
V003__create_students_schema.sql
...
```

### Schema Version Tracking

```
Application Version : 2.4.0
Database Version    : 2.4.0
Migration Number    : 124
```

## Load Testing (Pre-Production P0)

| Scenario | Metric to Capture |
|----------|------------------|
| Login | P95 response time |
| Search student | P95, query plan |
| Mark attendance (bulk) | Throughput, locks |
| Enter grades | P95 |
| Generate report | Queue time, completion |
| Dashboard | Cache hit rate |
| Bulk import 10K students | Total time, errors |

Test at 100, 500, 1000, 5000 concurrent users to find breaking point.

## Data Retention Policy

| Tier | Period | Action |
|------|--------|--------|
| HOT | Current 2–3 years | Primary DB, active |
| WARM | 3–10 years | Primary DB, may archive partitions |
| ARCHIVE | 10–20 years | Archive storage |
| COLD | 20+ years | Offline backup / object storage |

Official academic records retained per legal/administrative policy only.

## PostgreSQL Maintenance

```sql
-- Autovacuum handles most cases automatically
-- Tune for high-churn tables:
ALTER TABLE attendance.records SET (
  autovacuum_vacuum_scale_factor = 0.05
);

-- After bulk import/export:
ANALYZE attendance.records;

-- Reindex when bloat detected (not scheduled nightly):
REINDEX INDEX CONCURRENTLY ix_name;
```

Do **not** use SQL Server concepts: Index Reorganize, Index Rebuild on schedule.
