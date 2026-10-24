# SIS Exam / Results — Operational Task List (2026-10-24)

Every item below is **NOT DONE** unless its status says otherwise. Writing a procedure is not evidence that it works: an item
closes only when its "Evidence required" exists (a command output, a log, a screenshot, a signed note). Nothing here has been
run against the live `sis` database. Written for: the SIS owner / operator.

| # | Task | Status | Owner | Evidence required to close |
|---|---|---|---|---|
| T-1 | Restore rehearsal | NOT DONE | _unassigned_ | §1 |
| T-2 | Monitoring and alerts | NOT DONE | _unassigned_ | §2 |
| T-3 | Drift report by school / year / kind | DRAFT query only, never run | _unassigned_ | §3 |
| T-4 | Incident and post-publication correction procedures | WRITTEN (procedure), NOT REHEARSED | _unassigned_ | §4 |

## 1. T-1 — Backup restore rehearsal (isolated environment)

Goal: prove that a backup of `sis` restores with the exam/results tables intact, and measure the time.
Steps (all on a **copy host or a scratch PostgreSQL instance, never on `sis`**):
1. Take the backup the production job already takes (physical backup + WAL, per the project docs); note its timestamp and size.
2. Restore it to an isolated instance under a different database name; do not point any app at production credentials.
3. Verify, with read-only SQL, that these hold after restore (compare with counts taken on the source at backup time):
   - row counts of `results.result_publications`, `exams.grade_reviews`, `exams.exam_type_weight_versions`, `exams.student_grades`,
     `results.term_results`, `results.annual_results`, `results.gpa_results`, `results.transcripts`;
   - `SELECT relrowsecurity, relforcerowsecurity FROM pg_class` is true/true for the three new tables;
   - the guard triggers exist and are `ENABLE ALWAYS` (`tgenabled = 'A'`): `result_publications_guard`, `grade_reviews_guard`,
     `exam_type_weight_versions_guard`, and the `exams.student_grades` update trigger;
   - FK and CHECK constraints are present (`\d+` or `pg_constraint`);
   - for every active publication, `source_fingerprint` equals the SHA-256 recomputed from `published_snapshot` (script TODO);
   - one student's published result chain reads identically in the app pointed at the restored copy.
4. Point-in-time variant: restore to a moment before a known publication and confirm it is absent.
5. Record: backup id, restore start/end, measured duration, any discrepancy, who ran it.
Evidence required: the written record with the command outputs. Until then the claim "restorable" is **NOT VERIFIED**.

## 2. T-2 — Monitoring and alert design (design only; nothing is deployed)

| Signal | Source | Suggested alert | Notes |
|---|---|---|---|
| Publications with drift > 0 | drift query (§3), run every 15 min | warn at first, page if any lasts > 1 working day | students see a result that is no longer the official one |
| Review backlog age | `exams.grade_reviews` status `submitted`/`under_review`, oldest `requested_at` | warn when older than the school's agreed window (window is an owner decision, B) | no deadline is enforced in code |
| Failed calculations | audit/log lines with `results.term_weight_sum_invalid`, `results.official_dataset_incomplete`, `results.annual_no_term_results` | warn on bursts | weights not totalling 100 after a weight version |
| Weight version created | audit action `exams.web.types.weight_version` | notify the owner every time | policy change |
| Withdrawals | audit actions `results.web.year_sheet.withdraw` | notify the owner every time | |
| Unexpected deletes | trigger exceptions "never deleted" / "insert-only" in the PostgreSQL log | page | indicates a tool or person trying to rewrite history |
| 403/404 on `/portal/results` and `/api/v1/portal/results/*` | access log | warn on spikes | possible probing |
| DB role without RLS | `SELECT rolbypassrls, rolsuper FROM pg_roles WHERE rolname = <app role>` | page if true in production | RLS only protects when the app role is not superuser/BYPASSRLS |

Evidence required: the alert rules deployed in the monitoring system, and a test firing of each (screenshot or log). Not done.

## 3. T-3 — Drift report (DRAFT, not executed anywhere)

Purpose: list active publications whose snapshot no longer matches the current official result, by school, year and kind.
Draft for `annual` and `gpa`; `term` additionally joins `term_id`/`subject_id`; `transcript` uses `transcripts.is_current` and
`transcript_version`. To be run **read-only** by a role that may read all schools (a per-school run must set the school context;
RLS hides other schools otherwise), after review on a scratch database.

```sql
SELECT p.school_id, p.academic_year_id, p.kind, p.id AS publication_id, p.class_id, p.section_id,
       count(*) AS drifted_students
FROM results.result_publications p
CROSS JOIN LATERAL jsonb_array_elements(p.published_snapshot) AS e
WHERE p.status = 'published'
  AND p.kind = 'annual'
  AND NOT EXISTS (
        SELECT 1 FROM results.annual_results r
        WHERE r.enrollment_id = (e->>'enrollment_id')::bigint
          AND r.academic_year_id = p.academic_year_id
          AND r.is_current_official
          AND r.result_version = (e->>'version')::int)
GROUP BY 1, 2, 3, 4, 5, 6
ORDER BY 1, 2, 3, 4;
```

The application already computes the same condition per class on the results sheets (`drifted` in
`EloquentResultPublicationRepository`). Open decisions: where the cross-school report lives (an artisan command with an
explicit allowed-school list vs. a super-admin page), who may read it, and the output format. Evidence required: the reviewed
query/command, its output on a scratch database seeded with a known drift, and a note of who runs it and how often.

## 4. T-4 — Incident response and post-publication correction

Principles (already enforced by the system): publications and reviews are never deleted; official grades change only by
VOID + INSERT; a rebuilt result is hidden from students until it is published again; no stored result is recalculated without
explicit authorization.

| Situation | First response | Fix | Close-out |
|---|---|---|---|
| A wrong result is visible to students | withdraw the publication (reason required) at once | correct the grade (`grades.correct`) or rebuild the result, reconcile on the sheet, publish again | note the publication ids and the reason in the incident record |
| A published result changed afterwards (drift warning) | withdraw + republish after checking why it changed | find who rebuilt it (audit log) | confirm the drift count is 0 |
| A grade review is accepted | the grade is corrected, results are NOT recalculated | recalculate through the results sheet / rebuild with the owner's authorization, reconcile, republish | link review number ↔ resulting grade ↔ new result version |
| Wrong exam-type weight version | add a newer version with a reason | rebuild the affected results only with explicit authorization | version history keeps both |
| Suspected unauthorized access to results | check audit events `SEC_RESULTS_DATA_ACCESS`, `IDOR` events; disable the user's roles | review RLS role (T-2) | security review note |
| Data inconsistency suspected after a restore | stop publishing | run the checks of T-1 step 3 | restore decision by the owner |

Each procedure needs a named owner, a contact path and a rehearsal date. Not rehearsed: **NOT VERIFIED** that the steps work
end to end in a real incident.
