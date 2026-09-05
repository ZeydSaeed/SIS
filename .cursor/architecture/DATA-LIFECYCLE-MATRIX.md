# Data Lifecycle Matrix

> **Rule:** Retention and tiering follow policy + measured growth — not fixed year counts alone.

## Lifecycle Tiers

| Tier | Typical Age | Storage | Access Pattern | Query Target |
|------|-------------|---------|----------------|--------------|
| **HOT** | Current 1–2 academic years | Primary DB, active partitions | Daily | P95 < budget |
| **WARM** | 3–10 years | Primary DB or attached old partitions | Weekly/monthly | P95 < 1s |
| **ARCHIVE** | 10–20 years | Detached partitions / archive DB | Rare, compliance | < 5s acceptable |
| **COLD** | Legal retention limit | Object storage / offline backup | Legal only | Restore on demand |

Official academic records: **never hard-delete** unless legal/administrative policy mandates.

---

## Entity Lifecycle Rules

| Entity / Table | Create | Update | End of Life | Delete Policy |
|----------------|--------|--------|-------------|---------------|
| students.students | Admission | Profile corrections | Graduation/withdraw | Never — status inactive |
| enrollment.enrollments | Yearly enroll | Transfer, section change | Year end | Never — effective_to |
| attendance.records | Daily batch | Correction with audit | Year end | Archive partition |
| exams.student_grades | Exam entry | Correction with audit | Year end | Archive partition |
| audit.audit_logs | Every CRUD | Never | Continuous | Archive monthly partitions |
| finance.payments | Payment | Never | 7+ years | Archive per finance policy |
| communication.messages | Send | Never | 1–3 years | Partition drop per policy |
| certificates.issued | Issue | Revoke (status) | Permanent | Never |
| documents.files | Upload | Metadata update | With entity | Soft archive |

---

## Partition Lifecycle (Attendance Example)

```text
Year N (HOT)
  CREATE partition records_y{N} before enrollment opens
  Active reads/writes all year

Year N+1
  New partition records_y{N+1} becomes HOT
  records_y{N} → WARM

Year N+8 (example)
  DETACH records_y{N} → ARCHIVE storage
  Optional: attach to archive DB for queries

Year N+20
  COLD backup per legal policy
```

**Review trigger:** when any partition exceeds 50M rows, evaluate sub-partitioning (e.g. by school) — measurement required.

---

## Index Lifecycle

| Stage | Action |
|-------|--------|
| Create | INDEX-GOVERNANCE template + EXPLAIN evidence |
| Monitor | Quarterly pg_stat_user_indexes review |
| Unused 90 days | Review for drop |
| Table archived | Drop indexes on archived partitions with partition |

---

## Materialized View Lifecycle

| Stage | Action |
|-------|--------|
| Create | Prove query > 2s without MV |
| Operate | Monitor refresh duration + dashboard usage |
| Unused 6 months | Deprecate |
| Schema change | Revalidate MV definition |

---

## Cache Lifecycle

| Data Type | TTL | Invalidate On | Remove When |
|-----------|-----|---------------|-------------|
| Reference (subjects) | 1–4 h | Admin update | Query < 5ms without cache |
| Permissions | 15 m | Role change | Always keep short TTL |
| Dashboard | 5 m | Summary refresh | MV serves same data |

---

## Growth → Lifecycle Actions

| Signal | Lifecycle Action |
|--------|------------------|
| Table > 10M rows | Partition review |
| Partition > 50M rows | Sub-partition or archive review |
| Audit > 100M rows | Monthly partition + BRIN |
| DB > 80% disk | Archive WARM → ARCHIVE |
| Student count −50% | Right-size infra; keep indexes until usage proves waste |

---

## Related

- [DATABASE-ADAPTIVE-GOVERNANCE.md](./DATABASE-ADAPTIVE-GOVERNANCE.md)
- [01-principles-and-layers.md](./01-principles-and-layers.md)
- [capacity-planning.md](./capacity-planning.md)
- [security-audit-resilience.md](./security-audit-resilience.md)
