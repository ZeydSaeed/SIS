# Timetable — Domain Specification

**Status:** IMPLEMENTED 2026-10-07 (decisions D1–D5 approved; ADR-021, ADR-022). §6 is live — see
§6.1 for where the build differs from the design.
**Principle:** SIS-native. Timetable owns *scheduling* facts only; every school, person, subject,
room and curriculum fact stays in its owning context and is referenced by FK.

---

## 1. Ownership map

```text
Organization ── schools, branches, departments, rooms
Academic ────── academic_years, terms, holidays, grade_levels
Vocational ──── specializations, workshops, workshop_equipment
Enrollment ──── classes, sections, enrollments (student → section, branch, department)
Curriculum ──── subjects (subject_type), curricula, curriculum_subjects.weekly_hours, prerequisites
Teachers ────── teachers, teacher_schools, teacher_subjects, teaching_assignments, qualifications
                        │  (read through the Timetable projection — never copied)
                        ▼
Timetable ───── periods (school day) · schedules (grid) · schedule_exceptions (per date)
                + proposed: configs · activities · groups · availability · constraint_rules
                            versions · generation_runs
Attendance ──── sessions.period_id (reads the effective timetable; never written by Timetable)
```

## 2. Core concepts

| Concept | Meaning | Where it lives |
|---------|---------|----------------|
| **Projection** | Read model built from SIS sources for one school-year: sections, requirements, teachers, subjects, periods | `TimetableBoardLoader` → `TimetableBoard` (exists) |
| **Requirement** | section × subject × teacher × weekly load, derived from teaching assignments + curriculum | `TimetableBoard::$requirements` (exists, derived, not stored) |
| **Activity** | The schedulable unit: what is taught, to which groups, by which teachers, how long, how often, in what kind of room | proposed `timetable.activities` |
| **Card / Lesson** | One placed occurrence of an activity on (day, period[, week]) | today `timetable.schedules` row (one per period) |
| **Group** | A subset of a section's students inside a *division*; groups of the same division may run simultaneously | proposed `divisions` / `division_groups` |
| **Constraint rule** | Typed, scoped, prioritised rule (hard or soft) | proposed `constraint_rules` |
| **Version** | An immutable snapshot of a whole school-year timetable with a lifecycle and validity dates | proposed `versions` / `version_entries` |
| **Effective timetable** | Published version for a date + `schedule_exceptions` (+ future temporary changes) | read model |

### Activity (central concept)

```text
Activity
  subject_id            → curriculum.subjects
  activity_type         Theory | Practical | Laboratory | Workshop | Seminar | Tutorial | …  (SMALLINT, catalogue below)
  targets               1..n (section, group?)       → activity_sections   (joined classes = n sections)
  teachers              1..n (teacher, role, sessions) → activity_teachers (co-teaching, assistant;
                                                        sessions = how many of the weekly occurrences the
                                                        teacher attends, NULL = all  →  "5 lessons, B in 3")
  weekly_count          N occurrences per cycle
  distribution          e.g. "2+2+1"; fixed | flexible (engine may change)
  block_length          1 single · 2 double · 3 triple · …
  room requirement      room_type | specific room | workshop_id ; preferred / fallback rooms
  week_pattern          every week | odd | even | weeks bitmask | term_id
  source                curriculum (requirement id) | manual
  status                active | ended (effective_from / effective_to) — never deleted
```

Activities are **proposed** from requirements (curriculum weekly hours × teaching assignments) and
confirmed by a human (spec §39). An unconfirmed requirement still works as today (one activity per
requirement, singles, practical = doubles), so T1 is backward compatible.

**Activity type catalogue** (SMALLINT, extensible without migration via the catalogue enum in Domain):
1 theory · 2 practical · 3 laboratory · 4 workshop · 5 seminar · 6 tutorial · 7 exam slot ·
8 consultation · 9 planning · 10 meeting · 11 supervision · 12 substitute duty · 13 special ·
14 elective · 15 optional · 16 reserved · 17 event. *Co-teaching, joined classes, divided groups and
cross-class activities are not types* — they follow from the number of teachers / targets.

## 3. Time model

- **Days:** `timetable.configs.working_days` (SMALLINT[] 1–7; default Sun–Thu = 1–5, today's
  `SchoolWeek::DAYS`). Days stay ISO-like ordinals so attendance `session_date` maps by weekday.
- **Periods:** existing `timetable.periods` (lesson / break, ordered by `period_number`). Break kinds
  (normal / long / lunch / prayer) are a display property → additive `break_kind SMALLINT NULL` later.
- **Multiple bells** (by day, building, department): a *bell set* concept (`bell_sets` + `bell_set_id`
  on periods) — deferred; it must not change the SMALLINT PK used by attendance (HD-TV-004).
- **Cycles:** `configs.cycle_weeks` (1 = weekly, 2 = A/B, n). A card carries `week_no` (NULL = every
  week). Terms come from `academic.terms`; an activity may be bound to a `term_id`.
- **Doubles across breaks:** today a changeover ≤ 10 minutes joins a double (`SchoolWeek::MAX_DOUBLE_BREAK_MINUTES`);
  becomes a config value.

## 4. Constraint model

```text
constraint_rules
  rule_type     code from the Domain catalogue (e.g. teacher.max_per_day, subject.max_per_day,
                teacher.max_gaps, class.no_gaps, activity.before, activity.same_day, room.capacity …)
  priority      CRITICAL | VERY_HIGH | HIGH | MEDIUM | LOW | VERY_LOW   (SMALLINT 1–6)
                CRITICAL = hard (never relaxed); others soft with configurable weights
  scope         explicit nullable FKs: branch_id, department_id, specialization_id, grade_level_id,
                class_id, section_id, teacher_id, subject_id, room_id, activity_id, other_activity_id
                (CHECK: the columns allowed per rule_type are validated in Domain; no entity_type/entity_id)
  apply_to      each-teacher | each-class | each-room | global  (aSc "Apply to")
  params        JSONB validated by the rule_type's Domain class (e.g. {"max": 6}, {"periods": [1,2]})
  source        system | school | branch | department | specialization | grade | class | activity | ai-proposal
  active, reason, created_by, effective_from, effective_to
```

**Precedence (most specific wins for the same rule_type):**
`system < school < branch < department/specialization < grade < class < section < teacher/subject < activity`.
The compiler records, for every compiled constraint, the rule id it came from → "this rule comes from
Branch = Industrial, Specialization = Electricity" (spec §110).

**Built-in hard rules (not stored, always on):** teacher / section / room not double-booked; group
compatibility (only groups of one division together); lesson not in a break; teacher assigned the
subject and active; room capacity ≥ students (unless an explicit capacity override rule exists);
availability (time-off) respected.

Today's constants (`MAX_TEACHER_LESSONS_PER_DAY = 6`, `MAX_SUBJECT_LESSONS_PER_DAY = 2`, practical
doubles) become the **system defaults** of `teacher.max_per_day`, `subject.max_per_day`,
`activity.block_length` — so existing behaviour is the zero-configuration case.

## 5. Lifecycle & versioning

```text
DRAFT → VALIDATED → GENERATED → REVIEW → APPROVED → PUBLISHED → ACTIVE → ARCHIVED
                                     (any → SUPERSEDED when a newer version is published for the same dates)
```

- Working grid = `timetable.schedules` (today's behaviour; DRAFT of the next version).
- **Publish** = validate (Advisor not BLOCKED, audit has no errors) → snapshot the grid into
  `version_entries` → set `published_at`, `effective_from/to` → previous overlapping version gets
  `effective_to` = new `effective_from − 1` and SUPERSEDED. One transaction, idempotency key.
- Published entries are immutable (trigger rejects UPDATE/DELETE). Changes after publish = new version.
- Historical queries ("what did 2A have last week?") read the version effective on that date +
  exceptions on that date.
- **Stale detection:** each version stores a `source_fingerprint` (hash of requirements, sections,
  teachers, periods). The workspace compares it with the live projection → "Published timetable may be
  affected by source-data changes". Never regenerates automatically.

## 6. Schema (implemented)

All tables: schema `timetable`, `BIGINT IDENTITY` PK, `school_id NOT NULL` FK, FORCE RLS on
`app.current_school_id`, reject-DELETE trigger, `created_at/updated_at TIMESTAMPTZ`,
`restrictOnDelete` FKs, `academic_year_id` where academic.

| Table | Key columns | Notes |
|-------|-------------|-------|
| `configs` | school_id, academic_year_id, working_days SMALLINT[], cycle_weeks, double_changeover_minutes, policy JSONB | UNIQUE(school_id, academic_year_id) |
| `activities` | academic_year_id, subject_id, activity_type, weekly_count, block_length, distribution VARCHAR(20), distribution_fixed BOOL, room_type, room_id, workshop_id, week_pattern, term_id, status, effective_from/to | BTREE(school_id, academic_year_id) |
| `activity_sections` | activity_id, section_id, group_id NULL | UNIQUE(activity_id, section_id, COALESCE(group_id,0)) |
| `activity_teachers` | activity_id, teacher_id, role (lead/co/assistant), sessions NULL | UNIQUE(activity_id, teacher_id) |
| `divisions` | section_id, name | groups of one division may be simultaneous |
| `division_groups` | division_id, name, student_count | later `group_members(group_id, enrollment_id)` (T10) |
| `availability` | academic_year_id, teacher_id / room_id / section_id / workshop_id (exactly one, CHECK), day, period_id, week_no, kind (unavailable / preferred / avoid), reason | explicit FKs, no polymorphism |
| `constraint_rules` | see §4 | BTREE(school_id, academic_year_id, active) |
| `versions` | academic_year_id, version_no, parent_version_id, status, reason, source_fingerprint, quality JSONB, created_by, approved_by/at, published_by/at, effective_from/to, idempotency_key | UNIQUE(school_id, academic_year_id, version_no) |
| `version_entries` | version_id, section_id, group_id, day, period_id, week_no, subject_id, teacher_id, room_id, activity_id | immutable; BTREE(version_id, section_id), BTREE(version_id, teacher_id) |
| `generation_runs` | academic_year_id, mode, scope JSONB, solver, status, progress, input_fingerprint, snapshot_key (object storage), score JSONB, hard/soft counts, placed/unplaced, started/finished_at, requested_by, idempotency_key | partial UNIQUE(school_id, academic_year_id) WHERE status IN (queued, running) → one run at a time (spec §67) |
| `schedules` (ALTER, additive) | `locked_at TIMESTAMPTZ NULL`, `locked_by BIGINT NULL`, later `week_no`, `group_id`, `activity_id` | no unique/PK change in T1 |

**Not created:** no copies of teachers, rooms, subjects, sections or curricula; no `timetable_days`
(working days are a config array), no `timetable_conflicts` table (conflicts are computed),
no `timetable_locks` table (lock is a column).

### 6.1 Deviations from the design (decided during the build)

- **Joined classes:** one row per section on the grid; the other sections' rows carry `joined_to_schedule_id` → the
  lead row, which alone holds teacher / room occupancy (partial uniques count lead rows only).
- **Co-teaching:** `schedules.co_teacher_id` (one co-teacher per lesson, with its own partial unique);
  `activity_teachers.sessions` decides on how many blocks the co-teacher attends. Extra assistants beyond one are
  kept on the activity but not placed.
- **Group membership** (`group_members`) shipped now (student timetable), not deferred to T10.
- **Run snapshot** stored as JSONB on the run (`input_snapshot`) instead of object storage — a school-year snapshot
  is a few hundred KB; reproducible and simpler.
- **Lifecycle:** «validated / generated / active» are not stored states: validation is the advisor, generation is a
  run, «active» is the published version whose dates contain today.
- **Activity edits:** load / block / room / week change in place; targets and teachers change by ending the
  activity and creating a new one (history stays readable, join rows never deleted).

## 7. Application surface (naming follows the project)

Commands (implemented): `SaveTimetableSettings`, `Create/Update/EndTimetableActivity`, `SyncTimetableActivities`,
`SplitSectionIntoGroups`, `EndTimetableDivision`, `SetTimetableAvailability`, `SaveTimetableRule`, `EndTimetableRule`,
`QueueTimetableGeneration`, `ExecuteTimetableGeneration` (job), `Cancel/Apply/DiscardTimetableGeneration`,
`LockSchedules`, `CreateTimetableVersion`, `SubmitTimetableVersion`, `DecideTimetableVersion`,
`PublishTimetableVersion`, `ArchiveTimetableVersion`, `RestoreTimetableVersion`. Existing commands
(Create/Update/Swap/Shift/Cancel/AutoPlace) stay; the repository enforces locks and group / week / joined rules.
Queries: workspace (+ readiness / workload / quality), `GetTimetableEngine`, `GetGenerationRun`,
`CompareTimetableVersions`, `GetTimetableVersionEntries`, `SuggestScheduleMoves`, `SuggestSubstitutes`,
`GetStudentTimetable`, `GetEffectiveTimetable`.

## 8. Integration contracts

- **Attendance** asks `GetEffectiveDayTimetableQuery(school, date, section|teacher)` → (period, subject,
  teacher, room, group). Attendance keeps owning sessions/records.
- **Exams** stay in the Exams context; exam slots may *reserve* periods via an `activity_type = exam slot`
  activity only if Exams asks for it — no coupling to `exam_sessions`.
- **Substitution** extends `schedule_exceptions` (already per date) with suggestions ranked by the
  Domain (qualified via `teacher_subjects`, free in the slot, under daily limit, same branch).
- **Notifications** via the existing `communication` outbox events (`TimetableVersionPublished`, …).
