---
name: examis-project-protocol
description: Start, resume or close a session of the SIS EXAMIS program (exams, grades, results, question bank, online delivery, SurveyKing reference). Reads the persistent project state, checks git and environment drift, picks the next unit and gate, and updates the state and resume files before the session ends.
---

# EXAMIS project protocol

Governing plan: `docs/examis/EXAMIS-MASTER-PLAN.md`. SIS rules in `CLAUDE.md` / `AGENTS.md` take precedence.

## Start or resume

1. Read, in order: `docs/examis/EXAMIS-PROJECT-STATE.md`, `docs/examis/EXAMIS-NEXT-SESSION.md`,
   the pending and related entries of `docs/examis/EXAMIS-DECISIONS.md`.
2. Run `git branch --show-current`, `git log --oneline -3`, `git status --short`. Compare with the state file (§2) and
   `EXAMIS-NEXT-SESSION.md`. If they differ, write the difference into `EXAMIS-PROJECT-STATE.md` before anything else.
3. Re-verify any fact you will rely on (a file, route, migration, test result). A previous report is not evidence.
4. Name the current phase and the next unit. Check its gate in `docs/examis/EXAMIS-ACCEPTANCE-CRITERIA.md`.
   If the unit needs a pending decision (`DEC-P..`), stop and ask only that decision.

## Before any change

- Classify the change (CLAUDE.md gate) and load only the matching skills/rules.
- Write the unit plan: goal, what exists, files, rules, permissions, DB changes, tests, risks, acceptance, rollback.
- Database writes only on a throwaway database proven by name (`docs/examis/EXAMIS-OPERATIONS.md` §3). Never `sis`.
- SurveyKing stays read-only. Pages are rebuilt one small unit at a time (DEC-020) — never a bulk restore of the old pages,
  never more than one unit past its gate (DEC-028), never a real publication or notification (DEC-027).

## After a unit

- Run the unit's tests and record each command and result in `docs/examis/EXAMIS-TEST-REGISTER.md`
  (PASS only for runs actually made; NOT RUN / BLOCKED otherwise).
- Update the matrix row in `EXAMIS-PARITY-MATRIX.md`, add to `EXAMIS-CHANGELOG.md`, add decisions to `EXAMIS-DECISIONS.md`.

## Close the session

Update `EXAMIS-PROJECT-STATE.md` (phase, last unit, risks, next step) and `EXAMIS-NEXT-SESSION.md`
(date and time, branch, HEAD, git status, done / not done, files to read, checks, failed or unrun tests,
pending decisions, first safe action). Do not commit unless the owner asks.

Report to the owner in Arabic using the order in protocol §14: status, done, files, database, tests, security, UI, risks,
next unit, authorization.
