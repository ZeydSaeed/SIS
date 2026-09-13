# PHASE TV — WORKSHOPS SAFETY CAPACITY BALLOT
# HUMAN DESIGN DECISION (LOCKED UNDER «استمر»)

---

```text
Date: 2026-09-13
Human: «استمر» after DOC binary
Status: LOCKED — recommended defaults adopted
```

## Decisions

| ID | Question | Options | Chosen |
|----|----------|---------|--------|
| HD-TV-WS-001 | Open workshops now? | A yes · B hold | **A yes** |
| HD-TV-WS-002 | Schema home | A vocational.workshops · B organization.rooms only | **A vocational.workshops** |
| HD-TV-WS-003 | safety_capacity ≤ capacity | A DB CHECK · B app-only | **A DB CHECK + app validate** |
| HD-TV-WS-004 | room_id | A required · B optional FK | **B optional FK → rooms** |
| HD-TV-WS-005 | section_batches | A open · B HOLD | **B HOLD** |
| HD-TV-WS-006 | equipment / incidents | A open · B HOLD | **B HOLD** |
| HD-TV-WS-007 | Assignment enforce vs enrollments | A open · B HOLD | **B HOLD** (catalog first) |
| HD-TV-WS-008 | Hard delete | A allow · B reject | **B reject** |
| HD-TV-WS-009 | Permission | A new · B vocational.manage/view | **B reuse** |

## Implications

```text
IN: vocational.workshops + Create/List + FORCE RLS + safety CHECK
OUT: section_batches, equipment, schedule conflict vs workshop, auto-split
Blueprint objects: 90 → 91
```
