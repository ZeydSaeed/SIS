# PHASE TV — TIMETABLE / VOCATIONAL
# FINAL CLOSURE GATE

---

```text
Subphase: Phase TV — Timetable / Vocational Capacity
Status: CLOSED / ACCEPTED WITH CONDITIONS
Date: 2026-09-12
Blueprint objects: 90 (unchanged; physicalize + enrich only)
```

## Unit closure matrix

| Unit | Deliverable | Status |
|------|-------------|--------|
| TV-U01 | `timetable.periods` FORCE RLS + reject DELETE | CLOSED |
| TV-U02 | `timetable.schedules` + FORCE RLS + conflict uniques | CLOSED |
| TV-U03 | `timetable.schedule_exceptions` + FORCE RLS | CLOSED |
| TV-U04 | Create/Update/Cancel Schedule commands | CLOSED |
| TV-U05 | `vocational.*` FORCE RLS + reject DELETE | CLOSED |
| TV-U06 | Vocational Application catalog commands | CLOSED |
| TV-U07 | Create/Update ScheduleException commands | CLOSED |
| TV-U08 | Timetable staff JSON HTTP writers | CLOSED |
| TV-U09 | Vocational staff JSON HTTP catalog writers | CLOSED |
| TV-U10 | Timetable + Vocational staff JSON HTTP readers | CLOSED |

## Design lock compliance

| Invariant | Status |
|-----------|--------|
| INV-TV-01 attendance↔periods contract | PASS (SMALLINT PK kept) |
| INV-TV-02 school_id + FORCE RLS on new TV tables | PASS |
| INV-TV-03 no hard DELETE (status/cancel) | PASS |
| INV-TV-04 schedule conflict fail-closed | PASS (partial uniques) |
| INV-TV-05 schedules not grade SSOT | PASS |
| INV-TV-06 vocational SSOT in vocational.* | PASS |
| INV-TV-07 blueprint count 90 | PASS |

## Deferred conditions

```text
- Schedule-exception list HTTP
- Auto-generate / solver timetable
- periods.id → BIGINT IDENTITY rewrite
- Phase 8 (NOT OPENED)
- Student/guardian portal (privacy ballot)
```

**Completed after original gate:** TV-U07…U10; Phase 7.6 Application + staff HTTP readers/writers/rebuild.

## Absolute prohibitions still in force

```text
- No Phase 8 without separate AuthZ
- No exam.session.cancel / DEFAULT student_grades partition
- No silent mutate of official academic ledgers
```

## Recommended next program path

```text
1) Schedule-exception list HTTP, OR
2) Student/guardian portal (privacy AuthZ ballot), OR
3) Phase 8 — only after explicit human start AuthZ
```

```text
PHASE TV FINAL CLOSURE GATE: CLOSED / ACCEPTED WITH CONDITIONS
Timetable + Vocational Application + staff HTTP writers/readers complete.
```
