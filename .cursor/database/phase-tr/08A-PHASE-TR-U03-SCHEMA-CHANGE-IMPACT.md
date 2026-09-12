# PHASE TR — U03 SCHEMA CHANGE IMPACT

---

```text
Change: NONE on transfers tables
Semantic: EnrollmentStatus::TRANSFERRED = 3 (application VO; no DB CHECK on enrollments.status)
Risk: HIGH (cross-school enrollment mutate)
```

## Checklist

```text
[x] No hard delete
[x] Source closed via status + effective_to
[x] Destination enrollment created via EnrollmentRepository::save
[x] Dual-school RLS: bind school context when writing each side
[x] Unique transfer_records per request
[x] Idempotent CompleteTransfer
```
