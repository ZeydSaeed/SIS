# Timetable — Vocational Scheduling Specification

**Status:** IMPLEMENTED 2026-10-07 except templates (§3) — see the notes per rule. ✅ = live.

---

## 1. Existing vocational data used as-is

| Fact | Source |
|------|--------|
| Branch → department → specialization → track | `organization.branches`, `organization.departments`, `vocational.specializations`, `vocational.tracks` |
| Practical subject | `curriculum.subjects.subject_type = 3` ✅ (builder places them as doubles) |
| Specialization subjects | `vocational.specialization_subjects` (`is_required`, `credit_hours`) |
| Weekly load per department | `curriculum.curriculum_subjects.weekly_hours` on the department curriculum ✅ |
| Workshop capacity / safety | `vocational.workshops.capacity`, `safety_capacity` (CHECK ≤ capacity), optional `room_id` |
| Equipment | `vocational.workshop_equipment.quantity` per workshop |
| Teacher eligibility | `teachers.teacher_subjects` ✅ (enforced on create/update) + `teacher_qualifications` |
| Students per section and department | `enrollment.enrollments` (active) ✅ (builder shows them) |
| Theory → practice prerequisite | `curriculum.prerequisites` (subject ↔ prerequisite subject) |

No new "workshop" or "facility" entity is created: a workshop **is** the facility; its `room_id`
links it to the physical room when one exists. Labs and computer rooms are `organization.rooms` with
a `room_type`.

## 2. Rules

| Rule | Model | Hard / soft |
|------|-------|-------------|
| ✅ Practical block | `activities.block_length` (2 today, 3 or 4 configurable); block must not cross a long break | hard |
| ✅ Workshop capacity | students of the activity's targets ≤ `safety_capacity` (instructor ratio) and ≤ `capacity` | hard |
| ✅ Split group | when a section exceeds `safety_capacity`: proposed split into ⌈students / safety_capacity⌉ groups of one division (36 → 18 + 18), both groups get the activity; groups of one division may run in parallel only if a second workshop + teacher exist | proposal → human confirms |
| ◐ Equipment | activity needs `per_student` or `per_group` units of equipment X; units in the slot ≤ `quantity` | hard |
| ✅ Equipment / workshop time windows | `availability` rows for `workshop_id` (maintenance, shared use) | hard |
| ✅ Qualified teacher | lead teacher has `teacher_subjects` for the subject (and optionally a qualification type) | hard |
| ✅ Theory before practice | relation `activity.before` (same day / previous day / same week) from `curriculum.prerequisites` or explicit rule | soft (HIGH) by default; hard if configured |
| ✅ Practice before assessment | relation `activity.before` between practical activity and the assessment slot | soft |
| ✅ Spread practical | max 1 practical block of a subject per day; avoid consecutive days | soft |
| ✅ Morning practical | preferred periods for workshop activities | soft |

## 3. Templates (data, not code)

Templates are seed sets of `constraint_rules` + activity defaults, copied into a school on demand
and then freely editable: **General**, **Vocational**, **Industrial** (workshop blocks of 3, safety
splits), **Agricultural** (field practice blocks, weather-free mornings as preference),
**Commercial** (computer / accounting labs), **IT**, **Laboratory-heavy**, **Workshop-heavy**.
Nothing branches on the template name at runtime.

## 4. Required scenario (spec §147) — acceptance test plan

```text
School: vocational · Branch: Industrial · Specialization: Electrical · Level: 2nd year
Sections A, B — 36 students each · Electrical Workshop capacity 18 (safety 18) · Electrical Lab
Activities per section: Electrical Theory 3/week · Electrical Practice 4/week · Electrical Lab 2/week
```

1. Advisor ✅ (today): counts fit the week; practical has a double slot; teachers qualified.
2. Split (T9): Practice and Lab proposed as groups A1/A2, B1/B2 of 18 → human confirms.
3. Rules: Practice requires the workshop; Lab requires the lab; Theory before Practice (same week).
4. Availability: one teacher unavailable Tuesday; workshop maintenance Wednesday P1–P2.
5. Generate (Balanced) → verify: no hard violation, each group's practice in doubles inside the
   workshop, never two groups in the workshop at once, theory earlier in the week.
6. Display section, teacher and workshop views.
7. Teacher absence on a date → substitution suggestions ranked (qualified, free, under daily limit).
8. Repair → publish a new effective version; the previous one is SUPERSEDED, history kept.

✅ Steps 1–8 run in `TimetableSolverTest::the_vocational_scenario_…` (Domain) and `TimetableEnginePostgreSqlTest` (end to end: split, generate, apply, approve, publish, substitute API).

## 5. Not built (deliberately)

- **Templates** (§3): stored rule sets per school type are data that each school can create today with «القيود»;
  a seeded template library is left for when schools ask for one (no runtime branching on school type exists).
- **Equipment units per slot**: workshops are exclusive facilities with a safety capacity (covers the common case);
  counting `workshop_equipment.quantity` per student is not modelled yet.
