# Phase E — Supporting Modules

> **الحالة:** دليل مرجع — لا migration  
> **المتطلب:** Phase D مكتمل  
> **التالي:** [PHASE-F-PRODUCTION.md](./PHASE-F-PRODUCTION.md)

## الهدف

مالية، تواصل، workflow، مستندات، audit — دعم العمليات اليومية والامتثال.

## الجداول (12)

| Schema | الجداول | العدد |
|--------|---------|-------|
| finance | fee_types, student_fees, payments, transactions | 4 |
| communication | notification_templates, messages, notification_jobs | 3 |
| workflow | approval_flows, approval_requests | 2 |
| documents | files | 1 |
| audit | audit_logs, login_history | 2 |

## نقاط حرجة

### finance.payments — Idempotency P0

```sql
UNIQUE (idempotency_key)
CHECK (amount > 0)
```

Never duplicate payment on retry.

### finance.transactions — Partitioned

```
Partition by academic_year_id when volume grows
```

### communication.messages — Partitioned

```
Partition by created_at (monthly) — high volume notifications
Async queue for mass SMS/email (45K recipients)
```

### audit.audit_logs — Append-only

```
Partition by created_at (monthly)
BRIN index on created_at
Never delete — 20M rows over 10 years expected
```

### documents.files — Object Storage

```
PostgreSQL: metadata only (storage_key, file_hash SHA-256)
Files: S3-compatible storage
Never store PDF blobs in DB
```

## Workflow — Transfers & Approvals

```
transfer_requests → approval_flows (Principal → Directorate)
approval_requests tracks current_step
```

## Checklist قبل Phase F

- [ ] Payment idempotency keys enforced
- [ ] Mass notification via queue only
- [ ] Audit logs on all academic CRUD
- [ ] Correlation ID in audit entries
- [ ] Document hash verification on download
- [ ] database-blueprint.md synced

## مراجع

- `security-audit-resilience.md`
- `scalability-and-async.md`
- `database-blueprint.md` → finance, communication, workflow, documents, audit
