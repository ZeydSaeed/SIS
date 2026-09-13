# PHASE 8.2 — QUALIFICATION DOCUMENT ATTACH BALLOT

---

```text
Date: 2026-09-13
Human: «استمر» (DOC-U04 LIVE unblocks Phase 8.1 binary HOLD)
Unit: 8.2-U01 Attach document to teacher qualification
Status: LOCKED
Schema: NONE (uses existing document_storage_key)
```

## Decisions

| ID | Topic | Choice |
|----|-------|--------|
| HD-82-001 | Mechanism | Attach existing `documents` row (document_id) → copy `storage_key` |
| HD-82-002 | Document binding | entity_type=`qualification` AND entity_id=`qualification_id` |
| HD-82-003 | Qual status | Active only |
| HD-82-004 | Auth | `teachers.manage` |
| HD-82-005 | HTTP | `POST …/qualifications/{id}/attach-document` |
| HD-82-006 | Idempotency | Required |
| HD-82-007 | Replace prior key | Allowed (overwrite storage key) |
| HD-82-008 | Multipart in this unit | OUT — use DOC upload first |
| HD-82-009 | employee_code rename / multi-school | HOLD |
