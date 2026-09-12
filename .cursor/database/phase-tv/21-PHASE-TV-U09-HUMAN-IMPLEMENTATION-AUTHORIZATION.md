# PHASE TV — U09 HUMAN IMPLEMENTATION AUTHORIZATION

---

```text
Date: 2026-09-12
Human: «استمر»
Selected: Vocational staff JSON HTTP catalog writers (TV-U09)
Rejected for now: Results writer HTTP · Student portal · Phase 8
Status: GRANTED
```

## Why this path

```text
- TV-U06 Application catalog commands exist; HTTP still deferred
- Completes Phase TV product write surface with Timetable HTTP
- Soft deactivate only — no hard delete
```

## Scope (IN)

```text
Permission vocational.manage + vocational_manager role
VocationalPolicy + school access
Thin Api\VocationalController → existing Application handlers
Routes under /api/v1/vocational/*
X-School-Id + required X-Idempotency-Key
PG feature tests + AuthZ deny
```

## Scope (OUT)

```text
Vocational read/list HTTP
Results writer HTTP
Student portal
Hard delete
Phase 8
```
