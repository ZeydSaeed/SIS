# Phase 3C.12B — Concurrency Test Results

## Test inventory

| Class | File | Method |
|-------|------|--------|
| REAL CONCURRENCY | `GraduationParallelProcessConcurrencyPostgreSqlTest` | `parallel_processes_cannot_insert_duplicate_completion_outcomes` |
| REAL CONCURRENCY ×5 | same | `five_repeat_parallel_completion_outcome_races_are_deterministic` |
| SERIALIZED uniqueness | `GraduationConcurrencyIdempotencyPostgreSqlTest` | dual-PDO commit-then-insert cases |
| Retry / tenant / catalog | same | retry, cross-school FK, RLS FORCE count |

**Worker:** `tests/Support/Database/graduation_race_completion_outcome.php`  
**Method:** two Symfony `Process` children + filesystem barrier → overlapping INSERTs into `graduation.completion_outcomes`.

## Distinction

| Kind | Definition used here |
|------|----------------------|
| REAL CONCURRENCY | Separate OS processes, barrier release, overlapping INSERT against PostgreSQL |
| SERIALIZED UNIQUENESS | Dual PDO BEGIN; A insert+commit; then B insert → `23505` (no single-thread lock wait) |

Single-threaded dual-PDO “execute B while A open” **deadlocks** PHP (B blocks; A cannot commit). Parallel processes are the Windows-capable proof.

## Suite repeatability (exclusive `sis_test`)

Isolated sequential full suite (11 tests):

| Run | Result | Notes |
|-----|--------|-------|
| 1 | PASS | 11/11, 28 assertions |
| 2 | PASS | |
| 3 | PASS | |
| 4 | PASS | |
| 5 | PASS | |

Earlier contaminated loop (parallel with regression suite on same DB): `FAIL, PASS, PASS, PASS, PASS` — Run 1 Class **I** (migrate:fresh collision). Not a Graduation concurrency defect.

## Parallel race inner loop

`five_repeat_parallel_completion_outcome_races_are_deterministic` asserts exit codes `[0,1]` and row count `1` for five consecutive process-pair races **inside one PHPUnit process** — all green on exclusive runs.

## Persisted business state (not HTTP)

Loser always `23505`; winner leaves exactly one `completion_outcomes` row for `(school_id, enrollment_id)`.

## Verdict

```text
REAL CONCURRENCY PROOF: PASS
```

Scoped to completion_outcome insert races. Other Graduation writes covered by SERIALIZED uniqueness + constraints (see map).
