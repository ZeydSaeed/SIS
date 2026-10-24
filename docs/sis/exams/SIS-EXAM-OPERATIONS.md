# SIS Exam / Results — Operations Notes (2026-10-24)

Open operational work (restore rehearsal, alerts, drift report, incident rehearsal) is tracked in [SIS-EXAM-OPERATIONS-TASKS.md](SIS-EXAM-OPERATIONS-TASKS.md); none of it is done.

Scope: the tables and flows added by EXAMIS A1–C. Facts below come from the migrations and code; items marked **TODO**
are not done and must be decided before relying on the feature in production. Written for: the person operating SIS.

## 1. What is new in the database

| Table | Writes | Never |
|---|---|---|
| `results.result_publications` | publish (insert), withdraw (status + reason) | delete; edit the snapshot, scope or fingerprint; reuse a withdrawn row |
| `exams.grade_reviews` | open, take, decide, withdraw | delete; edit a decided review; edit the original request |
| `exams.exam_type_weight_versions` | insert a version | update or delete |

All three: `school_id`, RLS + FORCE RLS, guard triggers `ENABLE ALWAYS` (they also fire under `session_replication_role = replica`).
Migrations are additive; see `database-blueprint` / the migration headers for details.

## 2. Backup and restore

- The new tables are ordinary PostgreSQL tables in the same database: the existing physical backup + PITR covers them.
  Nothing is stored outside PostgreSQL (no files, no Redis state).
- Restore order does not matter beyond the existing FKs (they reference schools, years, classes, sections, users,
  `exams.student_grades (id, academic_year_id)`, `exams.exam_types (id, school_id)`).
- Because publication / review / version rows cannot be deleted, a *logical* restore of a single table must be done into a
  scratch database and compared; do not truncate them in production. **TODO:** rehearse a point-in-time restore on a copy and
  record the time it takes.

## 3. Permissions to grant (security seeder)

`results.publish` (role `results_publisher`), `grades.review.request` and `grades.review.decide` (role `grades_manager`),
exam-type weight versions use the existing `exam.type.manage`. After deploying, run `php artisan db:seed --class=SecurityPermissionSeeder --force`
(upsert only) — already done on `sis` for the migrations applied so far. Permissions are cached per user: a user who already
logged in sees new permissions after the cache entry expires or a new session.

## 4. Routine checks (SQL, read-only)

- Active publications whose official result changed since (drift) are shown on the results sheets; a school-wide list is **TODO**.
- Reviews open for a long time: `SELECT review_number, status, requested_at FROM exams.grade_reviews WHERE status IN ('submitted','under_review') ORDER BY requested_at` (run with the school context set).
- A weight version in force for a year: newest `version_no` per `(exam_type_id, academic_year_id)`.
- The weights of the exam types used by one subject in one term must total 100, otherwise calculation fails closed with
  `results.term_weight_sum_invalid`; check this *before* finalizing after creating a version.

## 5. Procedures

- **Wrong result already published:** withdraw the publication (reason required) → fix through the official path (correct the
  grade or rebuild the result) → publish again. Students see nothing for that scope while it is withdrawn.
- **Result rebuilt after publication:** the portal hides it automatically (a publication shows only the exact version it
  snapshotted); withdraw and publish again to show the new version.
- **Accepted grade review:** the grade is corrected (VOID + INSERT) but results are NOT recalculated automatically. Recalculate
  through the results sheet / rebuild, then reconcile and republish.
- **Wrong weight version:** add a newer version with the corrected weight and a reason; the old one stays as history. Stored
  results are untouched until they are rebuilt with explicit authorization.
- **Never** run bulk recalculation of stored results without the owner's separate written authorization.

## 6. Monitoring and alerts (TODO)

No dashboards or alerts exist yet for: publication/withdrawal counts, review backlog age, failed calculations
(`results.term_weight_sum_invalid`, `dataset_incomplete`), queue failures. Security events are written to the audit log:
`results.web.*.publish`, `results.web.year_sheet.withdraw`, `grades.web.review.*`, `exams.web.types.weight_version`.

## 7. Dependencies and limits

PostgreSQL (RLS; roles used by the app must NOT be superusers or have BYPASSRLS in production — local development uses a
superuser, so RLS is only proven by tests using `PostgreSqlRlsActor`), Laravel sessions, the Vite build (`npm run build` after
front-end changes; the served build must be rebuilt or new columns/pages do not appear). Not used and not required: Redis state,
queues, external services. Notifications are not implemented.

## 8. Known limits

- Concurrency of two staff publishing the same scope at the same instant is protected by the partial unique index (the second
  gets `results.publish.already_published`); load behaviour under many parallel requests is not measured.
- Only one browser pass was made (see the readiness report); no screen-reader audit.
