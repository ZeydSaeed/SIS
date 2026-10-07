# Timetable — Solver Specification

**Status:** IMPLEMENTED 2026-10-07 (ADR-021). `TimetableSolverInterface` → `HeuristicTimetableSolver`;
`ConstraintCompiler`; `GenerationRunner` (queued); `TimetableAuditor` + `TimetableQualityScorer` verify every result;
`suggestMoves()` powers «اقتراح أماكن». The legacy per-section `TimetableAutoPlacer` («توزيع تلقائي») is kept. No claim is made about aSc's internal algorithm — only its documented behaviour
(strict vs relaxed generation, improve functions that never remove lessons, advisor checks) is used
as a functional reference.

---

## 1. Pipeline

```text
Controller (thin, authorize)
  → GenerateTimetableCommand / Handler (idempotency, one run per school-year, queue)
    → GenerateTimetableJob (re-binds school context)
      → TimetableBoardLoader            (projection from SIS — snapshot + fingerprint)
      → TimetableAdvisor                (BLOCKED → stop with reasons, nothing generated)
      → ConstraintCompiler              (rules + defaults + availability → SolverProblem)
      → TimetableSolver (port)          (adapter: HeuristicTimetableSolver first)
      → TimetableAuditor + QualityScorer (verification, score)
      → generation_runs row (result, never applied automatically)
  → ApplyGenerationRunCommand (human) → schedules, in one transaction
```

The Domain owns `SolverProblem`, `SolverResult`, `TimetableSolver` (interface) and the compiler.
Adapters live in Domain (pure PHP heuristic) or Infrastructure (anything with IO / external runtime).

## 2. Port

```php
interface TimetableSolver
{
    public function solve(SolverProblem $problem, SolverOptions $options, ?SolverProgress $progress = null): SolverResult;
}
```

- `SolverProblem`: slots (day × period × week), cards to place (activity occurrence, length, candidate
  teachers/rooms, targets), locked cards, hard predicates, soft terms with weights, rule provenance.
- `SolverOptions`: mode, time budget, seed (determinism), preserve set, objectives.
- `SolverResult`: placements, unplaced (each with a **reason set**), relaxed soft rules, score
  breakdown, iterations, elapsed.
- `SolverProgress`: callback the job uses to write `progress` (placed / total, best score) and to poll
  cancellation.

## 3. Modes (spec §31)

| Mode | Behaviour | Starting grid | May relax |
|------|-----------|---------------|-----------|
| Strict | only fully compliant timetables; leaves cards unplaced otherwise | empty or preserved | nothing |
| Balanced | construct + local search on soft penalty | preserved | soft (by weight) |
| Relaxed | as Balanced, plus relaxes priorities ≤ chosen level, reports each relaxation | preserved | ≤ level |
| Optimize existing | never removes a placed lesson; moves to lower penalty (aSc "improve") | current | soft only |
| Repair | touches only cards in conflict + their neighbourhood | current | soft only |
| Partial regeneration | scope = class / teacher / subject / room / branch / specialization; others locked | current | per mode |
| What-if | any mode on a modified *copy* of the projection; result stored as a run, never applied | copy | per mode |

## 4. First adapter — `HeuristicTimetableSolver`

Start simple and measurable (spec §64):

1. **Ordering:** most-constrained first (fewest candidate slots × block length × shared resources).
2. **Construct:** place each card in the slot with the lowest incremental penalty among hard-feasible
   slots (generalises today's AutoPlacer, which already respects teacher/section/limits/doubles).
3. **Repair when stuck:** bounded ejection chains — move one blocking card elsewhere, recurse to depth
   *k*; locked cards never move.
4. **Improve:** local search (swap / move / kempe-chain on teacher–section conflicts) with simulated
   annealing or tabu tenure; deterministic with a seed.
5. **Stop:** time budget, no improvement for *n* iterations, or cancellation.

Promotion to CP-SAT / MIP happens only if measured quality or time on the 45K seed misses targets —
via an Infrastructure adapter behind the same port, and only with an ADR (new runtime).

## 5. Scoring

Hard violations make a solution infeasible (never traded). Soft penalty = Σ weight(priority) ×
violation size. Default weights (configurable per school in `configs.policy`):
`VERY_HIGH 100000 · HIGH 10000 · MEDIUM 1000 · LOW 100 · VERY_LOW 10`; `CRITICAL` is hard.

User-facing quality (implemented in `TimetableQualityScorer`) is reported separately from the
solver's penalty: feasibility, completeness, teacher / section compactness, subject distribution,
practical doubles, workload balance, overall mean, grade *infeasible → incomplete → complete*.
Room and workshop utilisation join when activities carry rooms (T9).

## 6. Explainability (spec §47–48)

- Every unplaced card carries the **reasons each candidate slot was rejected**, aggregated:
  e.g. `workshop busy ×3, teacher unavailable ×1, group has locked lesson ×1` with the entities
  and slots involved.
- **Pre-check first:** `TimetableAdvisor` already blocks counting impossibilities before search —
  overbooked section / teacher, subject over daily limit × days, practical without a double slot,
  unqualified / inactive teacher, missing periods (implemented).
- **No feasible solution:** report the bottleneck resources (demand vs free capacity per teacher, room,
  workshop, group) and run a deletion filter over soft/high rules to find a **minimal relaxation set**:
  "allowing X on Wednesday makes the timetable feasible".
- Suggested fixes are data (`code`, entities, proposed change); the UI renders them; AI may phrase
  them but never decides (spec §80).

## 7. Conflict object (computed, not stored)

```text
type · severity (error | warning | info) · entity_type + entity ids (section/teacher/room/subject)
· schedule_ids (card ids) · related ids · day · period · message code · suggested_fixes[] · can_auto_repair
```

`TimetableAuditor` already returns this shape minus `suggested_fixes`/`can_auto_repair`; those are
added in T7 by the swap/repair engine.

## 8. Intelligent swap & repair (T7)

Moving card *c* to slot *s*: evaluate in order (1) direct move, (2) swap with the occupant,
(3) move occupant to its best free slot, (4) alternative room, (5) alternative teacher among those
assigned the subject and free, (6) alternative period the same day. Each proposal carries Δ hard,
Δ soft and the cards it touches. Repair after absence / room loss uses the same neighbourhood
search restricted to affected cards.

## 9. Runs, snapshots, concurrency

- `generation_runs` with partial unique on (school, year) while queued/running → second request
  returns the running run (idempotent).
- Input snapshot = the serialized projection (object storage key + fingerprint) so a run is
  reproducible and unaffected by edits during generation.
- Applying a run fails if the live fingerprint changed since the snapshot (user re-runs or forces).
- HTTP never waits: single-section auto-place may stay synchronous (it is today, bounded and fast);
  anything wider is queued.

## 9.1 As built

- Rule catalogue (`ConstraintRuleCatalogue`): teacher max / min per day, max gaps, max consecutive, max days;
  section max / min per day, no gaps, end by lesson; subject max per day, not on consecutive days; forbidden /
  preferred slots (scoped by section level, teacher, subject, room, activity); activity before / same day / not same
  day / not same time; teachers not simultaneous. Built-in: no double booking (teacher, co-teacher, section lane,
  room / workshop), division compatibility, unavailable slots, room / workshop capacity, block contiguity, spread of
  an activity's blocks (MEDIUM).
- System defaults: teacher ≤ daily limit (HIGH), subject ≤ daily limit (HIGH), section no gaps (HIGH), teacher gaps
  (VERY_LOW). Modes: strict = HIGH+ hard; balanced = VERY_HIGH+; relaxed = CRITICAL only; optimize / repair start
  from the grid.
- Objectives in the generate dialog become extra rules for the run (fewer teacher gaps MEDIUM, no last lesson LOW,
  practicals in the morning LOW).
- What-if adds unavailability on a copy of the projection; a what-if run can never be applied.
- Apply refuses when the grid fingerprint changed since the run (`timetable.generation_stale`).

## 10. AI (T12)

```text
natural language → AI parser → structured constraint proposal (rule_type, scope, priority, params)
→ validation by the rule_type class → human confirmation → constraint_rules row (source = ai-proposal)
```

The constraint engine stays the source of truth; AI never writes schedules directly.
