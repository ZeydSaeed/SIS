# PHASE TV — DESIGN LOCK (workshops safety)

---

```text
Status: LOCKED
Date: 2026-09-13
Slice: TV-WORKSHOPS
Ballot: 27 LOCKED
```

## In

```text
- CREATE vocational.workshops (school_id, code, name, capacity, safety_capacity, room_id?, status)
- CHECK capacity > 0, safety_capacity > 0, safety_capacity <= capacity
- FORCE RLS + reject DELETE
- CreateWorkshop + ListWorkshops HTTP
- UNIQUE(school_id, code)
```

## Out

```text
- section_batches
- workshop_equipment / safety_incidents
- Assignment headcount enforcement HTTP
- Timetable↔workshop conflict solver
```

## Units

| Unit | Name | Status |
|------|------|--------|
| TV-U12 | Schema + RLS + Create/List | AUTHORIZED |
| TV-U13 | Closure | PENDING |
