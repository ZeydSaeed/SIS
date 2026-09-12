# PHASE TV — U10 HUMAN IMPLEMENTATION AUTHORIZATION

---

```text
Date: 2026-09-12
Human: «استمر»
Selected: Timetable + Vocational staff JSON HTTP readers (list/show)
Rejected for now: Rebuild Results HTTP · Student portal · Phase 8
Status: GRANTED
```

## Why this path

```text
- Writers exist (TV-U08/U09); staff cannot list/show catalogs yet
- Completes Phase TV product read surface
- No schema change; CQRS Queries over existing tables
```

## Scope (IN)

```text
Permissions: timetable.view, vocational.view
Application Queries + DTOs + repo read methods
GET /api/v1/timetable/schedules (+ show)
GET /api/v1/vocational/specializations (+ show with tracks/subject links)
Server pagination (max 100)
PG feature tests + AuthZ deny
```

## Scope (OUT)

```text
Schedule-exception list HTTP
Vocational subject catalog search UI
Solver / auto-generate
Phase 8 · Student portal
```
