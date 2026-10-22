# SIS — End-to-end test runbook (Playwright)

How to build the isolated test database, fill it with the synthetic school and run the browser suite. Nothing here
touches the live database (`sis`): every command refuses a database whose name does not start with `sis_e2e`.

## 1. Safety model

| Guard | Where |
|---|---|
| The reset/seed command refuses production, non-PostgreSQL, any name not starting with `sis_e2e`, and any name not listed in `SIS_DESTRUCTIVE_ALLOWED_DATABASES` (`ProtectedDatabaseGuard`) | `app/Console/Commands/E2eSeedCommand.php` |
| A `pg_dump` backup is taken before every reset and verified with `pg_restore --list` | same, `storage/app/e2e/backups/` |
| The reset clears data only (`sis:rebuild-demo-data` + the e2e calendar) — schema, constraints, RLS, roles and migrations stay | same |
| `sis:rebuild-demo-data` itself refuses any database `ProtectedDatabaseGuard` does not allow (sis is protected; others only through `SIS_DESTRUCTIVE_ALLOWED_DATABASES`) — it can no longer run on the live database | `RebuildDemoDataCommand`, `DestructiveCommandsGuardPostgreSqlTest` |
| Playwright starts its own PHP server on the e2e database; it never reuses a running server (`php artisan serve` is not used: it does not pass `DB_*` to its child and would serve the live database) | `playwright.config.ts` |
| The suite's setup fails unless the server's database is `E2E_DB` and the seeded batch is there (200 students, 100 teachers, 5 personas) | `e2e/auth.setup.ts` |
| Tests read the database through a read-only helper (one `SELECT`, `READ ONLY` transaction, rolled back, e2e databases only); they change data only through the pages and the application's own endpoints | `e2e/support/db-query.php` |
| Persona passwords come from `SIS_E2E_PASSWORD` and are never written to the repository; every other account gets a random password | `database/seeders/E2eUsersSeeder.php` |
| The e2e server runs with `SECURITY_OPS_BOOTSTRAP_ENABLED=false` (no self-service roles, as in production) | `playwright.config.ts` |

All data is synthetic. Records created by tests carry «اختبار» and the run tag (`E2E_RUN`, base-36 start time) in
their names or codes.

## 2. One-time setup

```bash
# PostgreSQL client tools (adjust the path)
export SIS_PG_DUMP="D:/PostgreSQL/18/bin/pg_dump.exe"
"D:/PostgreSQL/18/bin/createdb.exe" -h 127.0.0.1 -U postgres -E UTF8 -T template0 sis_e2e

# Schema through the project's migrations only
DB_CONNECTION=pgsql DB_DATABASE=sis_e2e SIS_DESTRUCTIVE_ALLOWED_DATABASES=sis_e2e php artisan migrate --force

npm ci && npx playwright install chromium
npm run build          # the PHP server serves public/build
```

## 3. Reset and seed (repeatable)

```bash
export DB_CONNECTION=pgsql DB_DATABASE=sis_e2e SIS_DESTRUCTIVE_ALLOWED_DATABASES=sis_e2e
export SIS_E2E_PASSWORD="$(php -r 'echo bin2hex(random_bytes(12));')"   # keep it for the test run
php -d memory_limit=2G artisan sis:e2e-seed            # ~2–3 min; --no-backup to skip pg_dump
```

PowerShell:

```powershell
$env:DB_CONNECTION='pgsql'; $env:DB_DATABASE='sis_e2e'; $env:SIS_DESTRUCTIVE_ALLOWED_DATABASES='sis_e2e'
$env:SIS_E2E_PASSWORD = php -r 'echo bin2hex(random_bytes(12));'
php -d memory_limit=2G artisan sis:e2e-seed
```

What it builds (through the real command handlers — admission → student → enrollment, teachers, timetable,
attendance sheets, leave lifecycle):

| Data | Count |
|---|---|
| Students (admission → conversion → enrollment; accepted / waiting / rejected / withdrawn / suspended) | 200 (167 enrollments) |
| Teachers (T-S001…T-S050 timetable scenario, T-E051…T-E100: substitute pool, second school, titled, inactive, newly hired) | 100 |
| Schools · branches · departments · classes · sections | 2 · 8 · 24 · 3 · 9 (A/B/C) |
| Subjects · curricula · curriculum subjects | 47 · 64 · 507 |
| Rooms / labs / workshops | 21 rooms + 7 workshops |
| Timetable lessons (every state: complete, partly placed, over-placed, unqualified teacher, rooms too small, an overloaded teacher) | 252 |
| Attendance: sessions · records · excuses · staff days (12 school days, every sheet state and mark) | ≈553 · ≈8.3k · 12 · ≈946 |
| Leave requests (every type, status and approval path) · holidays (one cancelled) | 104 · 8 |
| Personas | admin, school2, viewer, teacher, noroles (`e2e-*@sis.test`) |

The command ends with `sis:audit-data-quality` (≈40 integrity checks) and its own expectations (exact totals, no
hard conflict in a published timetable, no orphan attendance or leave rows); counts before/after and the per-module
summaries are written to `storage/app/e2e/seed-report.json`, with **fingerprints**: an md5 of the ordered business
identities of students, applications, teachers, enrollments, curriculum, timetable, attendance, staff days, leave,
holidays and rooms (no ids, no timestamps). Two consecutive runs on the same day give identical counts in every table
and identical fingerprints; attendance and staff days are relative to «today», so those two fingerprints change from one
day to the next by design.

## 4. Run the suite

```bash
export SIS_E2E_PASSWORD=…           # the one used by sis:e2e-seed
npx playwright test                 # setup + desktop + tablet + phone (≈30 min, one worker)
npx playwright test e2e/timetable   # one module
npx playwright test --project=setup --project=mobile   # the @responsive pages on a phone
npx playwright show-report          # HTML report (traces, screenshots and videos of failures)
```

Run the reset before a full run: the suite writes data (tagged records, a published timetable version, cancelled
lessons) and some tests expect the seeded state (for example the timetable test report).

Optional variables: `E2E_DB` (default `sis_e2e`), `E2E_PORT` (8123), `E2E_BASE_URL`, `E2E_RUN` (run tag).

### 4.1 Grade-entry sheet (`e2e/grades/grade-sheet.spec.ts`) — disposable copy only

The spec needs exam fixtures that are **never** written to `sis` or to the shared `sis_e2e`. `sis:e2e-seed-exams`
checks, in this order and before any write:

1. on configuration only (no query): not production · PostgreSQL · the name is `sis_e2e_<suffix>` (`sis` and `sis_e2e`
   are always refused) · the name is in `SIS_DESTRUCTIVE_ALLOWED_DATABASES` and not protected · `SIS_E2E_PASSWORD` is set;
2. with read-only queries: the e2e dataset is there (the two schools and the admin personas), then the placement and
   the students the fixture will seat.

On `sis_e2e` the spec is skipped with that reason.

The copy is created and dropped only through `sis:e2e-disposable`, which works on the server's `postgres` database and
refuses any name that is not `sis_e2e_<suffix>` on the allow-list. `create` clones `sis_e2e` (no migration runs; `sis_e2e`
is only read and nothing may be connected to it) and writes a marker (`storage/app/e2e/disposable/<name>.json`) with the
new database's OID. `drop` refuses without that marker, when the live OID differs (the database was re-created by
something else), when the marker belongs to another server, or for a template — and without `--execute` it is only a
dry run.

```bash
export DB_CONNECTION=pgsql DB_DATABASE=sis_e2e_exams SIS_DESTRUCTIVE_ALLOWED_DATABASES=sis_e2e_exams
export SIS_E2E_PASSWORD="$(php -r 'echo bin2hex(random_bytes(12));')"

# 1. Disposable copy of the migrated e2e database
php artisan sis:e2e-disposable create sis_e2e_exams

# 2. Synthetic dataset with known persona passwords, then the exam fixture (adds a new tagged set every run)
php -d memory_limit=2G artisan sis:e2e-seed --no-backup
php artisan sis:e2e-seed-exams          # → storage/app/e2e/exam-fixture.json (database name + ids)

# 3. The specs (setup signs in the personas; the grade teacher exam-grader@sis.test signs in once inside the grade spec)
E2E_DB=sis_e2e_exams npx playwright test e2e/exams e2e/grades --project=chromium

# 4. Drop the copy when done — check the dry run, then execute
php artisan sis:e2e-disposable drop sis_e2e_exams
php artisan sis:e2e-disposable drop sis_e2e_exams --execute
```

If a step fails, the copy stays as it is (nothing is dropped automatically); drop it with step 4 once you no longer need it.

Run `sis:e2e-seed-exams` again before every run of the spec: grades cannot be deleted, so the spec refuses a fixture
whose seats were already graded («fixture … is not fresh») instead of passing on stale data. The fixture adds one
synthetic account (`exam-grader@sis.test`, role `grades_teacher`) and one synthetic teacher (`T-X901`) — outside the
persona and teacher counts the setup checks.

## 5. What a test may assume

- Every page is watched: a JavaScript error, a console error or an unexpected HTTP ≥ 400 from the application fails
  the test (`e2e/support/test.ts`); an expected refusal is declared with `watch.allow(pattern, status)`.
- Writes go through the page or through the application's own endpoints with the signed-in session and CSRF token
  (`e2e/support/http.ts` — `attempt()` returns the status and the error codes, whether the server answered with a
  422 or with the web pages' redirect-back-with-errors).
- Queued work (curriculum applied to enrollments, timetable generation) is processed by `runQueue()` — the e2e
  server runs no worker.
- No `waitForTimeout`: tests wait for an element, a response or `expect.poll` on the database.

## 6. What the browser tests do not prove: RLS

The local application connects as the PostgreSQL superuser (`BYPASSRLS`): row level security applies to no application
query here, so the browser tests prove the application's own school scoping, not RLS. RLS is proven by the PHPUnit
suites that switch to the non-privileged `sis_rls_tester` role (`PostgreSqlRlsActor`). Which role production connects
with is not recorded in this repository.

## 7. Troubleshooting

| Symptom | Cause |
|---|---|
| Setup: `expected 200 … received 201` | A record created by a test does not carry «اختبار» — or reset first |
| Sign-in times out | First request on a cold single-threaded server; the setup waits 30 s |
| Page changes are not visible | `npm run build` (the server serves `public/build`) |
| `port 8123 already in use` | Another server is running; the suite never reuses one — stop it or set `E2E_PORT` |
| `db-query: refused` | `E2E_DB` / `DB_DATABASE` is not an e2e database |

Related: [SIS-DATA-AND-UI-COVERAGE.md](SIS-DATA-AND-UI-COVERAGE.md) (coverage register, defects, open questions).
