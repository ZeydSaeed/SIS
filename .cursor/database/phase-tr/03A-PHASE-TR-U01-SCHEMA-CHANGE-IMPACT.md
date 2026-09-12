# PHASE TR — U01 SCHEMA CHANGE IMPACT

---

```text
Change: CREATE transfers.transfer_requests + transfer_records
         + FORCE RLS dual-school
         + reject hard DELETE
Type: CREATE + SECURITY
Blueprint: 2 objects (already counted) — records gain from/to school_id
Risk: MEDIUM–HIGH (cross-school visibility)
```

## Checklist

```text
[x] Blueprint defined
[x] PK BIGINT IDENTITY
[x] academic_year_id on requests
[x] FK restrict
[x] SMALLINT status
[x] TIMESTAMPTZ
[x] No hard-delete — triggers
[x] CHECK from_school <> to_school; status 1–5
[x] Dual-school FORCE RLS
[x] No CompleteTransfer writer
[x] No partition
```
