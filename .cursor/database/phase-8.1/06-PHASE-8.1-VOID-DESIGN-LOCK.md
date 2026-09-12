# PHASE 8.1 — DESIGN LOCK (qualification void)

---

```text
Status: LOCKED
Date: 2026-09-13
Slice: 8.1-VOID
Ballot: 05 LOCKED
```

## In

```text
- Columns: status SMALLINT NOT NULL DEFAULT 1, effective_from TIMESTAMPTZ, effective_to TIMESTAMPTZ
- Backfill: existing rows status=1, effective_from=created_at
- Index: BTREE(teacher_id, status) for list/filter
- VoidTeacherQualification (idempotent key; Active→Voided + effective_to)
- Route: POST /api/v1/teachers/{teacher}/qualifications/{qualification}/void
- List exposes status, effective_from, effective_to
```

## Out

```text
- Hard DELETE
- Document binary upload
- RLS on teacher_qualifications body
```

## Units

| Unit | Name | Status |
|------|------|--------|
| 8.1-U03 | Schema + Void HTTP | CLOSED |
| 8.1-U04 | Closure | CLOSED (see 09) |
