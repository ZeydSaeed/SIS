# SIS — End-to-end test runbook (Playwright)

How to build the isolated test database, fill it with the synthetic school and run the browser suite. Nothing here
touches the live database (`sis`): every command refuses a database whose name does not start with `sis_e2e`.

## 1. Safety model

| Guard | Where |
|---|---|
| The reset/seed command refuses production, non-PostgreSQL, any name not starting with `sis_e2e`, and any name not listed in `SIS_DESTRUCTIVE_ALLOWED_DATABASES` (`ProtectedDatabaseGuard`) | `app/Console/Commands/E2eSeedCommand.php` |
| A `pg_dump` backup is taken before every reset and verified with `pg_restore --list` | same, `storage/app/e2e/backups/` |
| The reset clears data only (`sis:rebuild-demo-data` + the e2e calendar) — schema, constraints, RLS, roles and migrations stay | same |
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
summaries are written to `storage/app/e2e/seed-report.json`. Two consecutive runs give identical counts in every
table.

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

## 5. What a test may assume

- Every page is watched: a JavaScript error, a console error or an unexpected HTTP ≥ 400 from the application fails
  the test (`e2e/support/test.ts`); an expected refusal is declared with `watch.allow(pattern, status)`.
- Writes go through the page or through the application's own endpoints with the signed-in session and CSRF token
  (`e2e/support/http.ts` — `attempt()` returns the status and the error codes, whether the server answered with a
  422 or with the web pages' redirect-back-with-errors).
- Queued work (curriculum applied to enrollments, timetable generation) is processed by `runQueue()` — the e2e
  server runs no worker.
- No `waitForTimeout`: tests wait for an element, a response or `expect.poll` on the database.

## 6. Troubleshooting

| Symptom | Cause |
|---|---|
| Setup: `expected 200 … received 201` | A record created by a test does not carry «اختبار» — or reset first |
| Sign-in times out | First request on a cold single-threaded server; the setup waits 30 s |
| Page changes are not visible | `npm run build` (the server serves `public/build`) |
| `port 8123 already in use` | Another server is running; the suite never reuses one — stop it or set `E2E_PORT` |
| `db-query: refused` | `E2E_DB` / `DB_DATABASE` is not an e2e database |

Related: [SIS-DATA-AND-UI-COVERAGE.md](SIS-DATA-AND-UI-COVERAGE.md) (coverage register, defects, open questions).
