<?php

namespace App\Console\Commands;

use App\Database\Exceptions\ProtectedDatabaseException;
use App\Database\ProtectedDatabaseGuard;
use App\Database\SchemaHelper;
use Database\Seeders\E2eAttendanceSeeder;
use Database\Seeders\E2eTeachersSeeder;
use Database\Seeders\E2eUsersSeeder;
use Database\Seeders\LeaveDemoSeeder;
use Database\Seeders\SecurityPermissionSeeder;
use Database\Seeders\SisFoundationSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Builds the end-to-end test dataset on an isolated database (sis_e2e…) — never on the live one:
 *
 * 1. guard: not production · PostgreSQL · name starts with «sis_e2e» · allowed by {@see ProtectedDatabaseGuard}
 *    (SIS_DESTRUCTIVE_ALLOWED_DATABASES must list it);
 * 2. a pg_dump backup of the current state, verified with pg_restore --list;
 * 3. reference data (foundation + roles), then `sis:rebuild-demo-data` (data only — schema, constraints, RLS and
 *    permissions stay) and the e2e layers: personas, teachers 51–100, leave, attendance;
 * 4. `sis:audit-data-quality` + the expected totals; counts before/after go to storage/app/e2e/seed-report.json.
 *
 * Synthetic data only. Persona passwords come from SIS_E2E_PASSWORD (never stored in the repository).
 *
 * DB_DATABASE=sis_e2e SIS_DESTRUCTIVE_ALLOWED_DATABASES=sis_e2e SIS_E2E_PASSWORD=… php artisan sis:e2e-seed
 */
class E2eSeedCommand extends Command
{
    protected $signature = 'sis:e2e-seed
                            {--batch=sis-e2e-2026-01 : Batch id (idempotency keys, report)}
                            {--no-backup : Skip the pg_dump backup taken before the reset}';

    protected $description = 'Reset and seed the isolated end-to-end database (200 students, 100 teachers, timetable, attendance, leave)';

    public const STUDENTS = 200;

    public const TEACHERS = 100;

    /** Tables counted before and after (schema.table). */
    private const STAT_TABLES = [
        'organization.schools', 'organization.branches', 'organization.departments', 'vocational.specializations',
        'academic.grade_levels', 'academic.academic_years', 'academic.holidays',
        'enrollment.classes', 'enrollment.sections',
        'curriculum.subjects', 'curriculum.curricula', 'curriculum.curriculum_subjects',
        'organization.rooms', 'vocational.workshops',
        'admission.application_periods', 'admission.applications',
        'students.students', 'enrollment.enrollments', 'enrollment.enrollment_subjects',
        'teachers.teachers', 'teachers.teacher_schools', 'teachers.teacher_subjects', 'teachers.teaching_assignments',
        'timetable.periods', 'timetable.schedules',
        'attendance.sessions', 'attendance.records', 'attendance.excuses', 'attendance.staff_records', 'attendance.sheet_events',
        'leave.types', 'leave.policies', 'leave.requests', 'leave.request_events', 'leave.balance_entries',
        'public.users', 'security.user_roles', 'audit.outbox_messages', 'audit.audit_logs',
    ];

    /** Tables filled by test runs (and the engine) that the demo rebuild leaves in place. */
    private const RUN_TABLES = [
        'timetable.version_entries', 'timetable.versions', 'timetable.generation_runs', 'timetable.test_marks',
        'workflow.approval_requests', 'attendance.alerts', 'documents.files',
    ];

    public function handle(): int
    {
        $refusal = $this->refusal();
        if ($refusal !== null) {
            $this->components->error($refusal);

            return self::FAILURE;
        }
        $database = DB::getDatabaseName();
        $batch = (string) $this->option('batch');
        $this->components->info("E2E seed «{$batch}» on {$database}");

        $report = ['batch' => $batch, 'database' => $database, 'started_at' => now()->toIso8601String(), 'before' => $this->counts()];

        if (! $this->option('no-backup')) {
            $report['backup'] = $this->backup($database);
        }

        $this->runSeeder(app(SisFoundationSeeder::class));
        $this->runSeeder(app(SecurityPermissionSeeder::class));
        // Run data the demo rebuild does not clear but which points at the rows it recreates under new ids: left
        // behind, a published timetable version kept its header with no lessons, alerts and leave files pointed at
        // other students / requests. The list is explicit; its foreign keys reach only each other and attendance
        // excuses (cleared by the rebuild anyway). Reference data, roles and the audit trail are kept.
        DB::statement('TRUNCATE '.implode(', ', array_map(static fn (string $t): string => SchemaHelper::qualified(...explode('.', $t)), self::RUN_TABLES)).' RESTART IDENTITY CASCADE');
        if ($this->call('sis:rebuild-demo-data') !== self::SUCCESS) {
            $this->components->error('sis:rebuild-demo-data failed.');

            return self::FAILURE;
        }

        // The calendar's holidays are not part of the demo rebuild: on the e2e database they are rebuilt with the leave
        // data (tests add their own holidays; nothing references the table).
        DB::table(SchemaHelper::qualified('academic', 'holidays'))->delete();

        $users = app(E2eUsersSeeder::class);
        $this->runSeeder($users);
        $teachers = app(E2eTeachersSeeder::class)->forBatch($batch);
        $this->runSeeder($teachers);
        $users->linkTeacherPersona();
        $this->runSeeder(app(LeaveDemoSeeder::class));
        $attendance = app(E2eAttendanceSeeder::class)->forBatch($batch, $users->adminId());
        $this->runSeeder($attendance);

        $report['after'] = $this->counts();
        $report['teachers'] = $teachers->summary;
        $report['attendance'] = $attendance->summary;
        $report['expectations'] = $this->expectations();
        $report['fingerprints'] = $this->fingerprints();
        $report['audit_exit_code'] = $this->call('sis:audit-data-quality');
        $report['finished_at'] = now()->toIso8601String();

        $path = storage_path('app/e2e/seed-report.json');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->table(['Table', 'Before', 'After'], array_map(
            static fn (string $t): array => [$t, (string) ($report['before'][$t] ?? '-'), (string) ($report['after'][$t] ?? '-')],
            self::STAT_TABLES,
        ));
        $failed = array_filter($report['expectations'], static fn (array $e): bool => ! $e['ok']);
        foreach ($report['expectations'] as $label => $e) {
            $this->components->twoColumnDetail($label, ($e['ok'] ? '<fg=green>OK</>' : '<fg=red>FAIL</>').' expected '.$e['expected'].', actual '.$e['actual']);
        }
        $this->components->info('Report: '.$path);

        return $failed === [] && $report['audit_exit_code'] === self::SUCCESS ? self::SUCCESS : self::FAILURE;
    }

    /** Null when the current connection is a disposable e2e database; otherwise why it is refused. */
    private function refusal(): ?string
    {
        if (app()->environment('production')) {
            return 'Refused: the e2e dataset is never built in production.';
        }
        if (! SchemaHelper::isPostgreSql()) {
            return 'Refused: requires PostgreSQL.';
        }
        $database = strtolower((string) DB::getDatabaseName());
        if (preg_match('/^sis_e2e[a-z0-9_]*$/', $database) !== 1) {
            return "Refused: «{$database}» is not an e2e database (the name must start with sis_e2e).";
        }
        try {
            app(ProtectedDatabaseGuard::class)->assertSafeForDestructiveOperations();
        } catch (ProtectedDatabaseException $e) {
            return 'Refused: '.$e->getMessage().' Add it to SIS_DESTRUCTIVE_ALLOWED_DATABASES.';
        }

        return null;
    }

    /** @return array<string, int|null> */
    private function counts(): array
    {
        DB::statement("SELECT set_config('app.current_school_id', '', false)");
        $counts = [];
        foreach (self::STAT_TABLES as $table) {
            [$schema, $name] = explode('.', $table);
            $exists = DB::selectOne('SELECT 1 AS ok FROM information_schema.tables WHERE table_schema = ? AND table_name = ?', [$schema, $name]) !== null;
            $counts[$table] = $exists ? (int) DB::table(SchemaHelper::qualified($schema, $name))->count() : null;
        }

        return $counts;
    }

    /** @return array<string, array{expected: int, actual: int, ok: bool}> */
    private function expectations(): array
    {
        $count = static fn (string $schema, string $table): int => (int) DB::table(SchemaHelper::qualified($schema, $table))->count();
        // A published version (status 5) is what the school runs: it must never hold a hard conflict.
        $published = static fn (string $who): int => (int) DB::selectOne("SELECT count(*) AS n FROM (
            SELECT e.version_id, e.{$who}, e.day_of_week, e.period_id, coalesce(e.week_no, 0)
              FROM timetable.version_entries e JOIN timetable.versions v ON v.id = e.version_id
             WHERE v.status = 5".($who === 'section_id' ? ' AND e.group_id IS NULL' : '').'
             GROUP BY 1, 2, 3, 4, 5 HAVING count(*) > 1) q')->n;
        $checks = [
            'students' => [self::STUDENTS, $count('students', 'students')],
            'teachers' => [self::TEACHERS, $count('teachers', 'teachers')],
            'teacher double-bookings in a published version' => [0, $published('teacher_id')],
            'section double-bookings in a published version' => [0, $published('section_id')],
            'room double-bookings in a published version' => [0, (int) DB::selectOne('SELECT count(*) AS n FROM (
                SELECT e.version_id, e.room_id, e.day_of_week, e.period_id, coalesce(e.week_no, 0)
                  FROM timetable.version_entries e JOIN timetable.versions v ON v.id = e.version_id
                 WHERE v.status = 5 AND e.room_id IS NOT NULL
                 GROUP BY 1, 2, 3, 4, 5 HAVING count(DISTINCT e.section_id) > 1) q')->n],
            'attendance records without a session' => [0, (int) DB::selectOne('SELECT count(*) AS n FROM attendance.records r WHERE NOT EXISTS (SELECT 1 FROM attendance.sessions s WHERE s.id = r.session_id)')->n],
            'attendance records of a student outside the section' => [0, (int) DB::selectOne('SELECT count(*) AS n FROM attendance.records r JOIN attendance.sessions s ON s.id = r.session_id JOIN enrollment.enrollments e ON e.id = r.enrollment_id WHERE e.section_id IS DISTINCT FROM s.section_id')->n],
            'published timetable version without lessons' => [0, (int) DB::selectOne('SELECT count(*) AS n FROM timetable.versions v WHERE v.status = 5 AND NOT EXISTS (SELECT 1 FROM timetable.version_entries e WHERE e.version_id = v.id)')->n],
            'attendance alert of an unknown student' => [0, (int) DB::selectOne("SELECT count(*) AS n FROM attendance.alerts a WHERE a.subject_type = 'student' AND NOT EXISTS (SELECT 1 FROM students.students s WHERE s.id = a.subject_id)")->n],
            'leave document of an unknown request' => [0, (int) DB::selectOne("SELECT count(*) AS n FROM documents.files f WHERE f.entity_type = 'leave_request' AND NOT EXISTS (SELECT 1 FROM leave.requests r WHERE r.id = f.entity_id)")->n],
            'leave requests of an unknown teacher' => [0, (int) DB::selectOne('SELECT count(*) AS n FROM leave.requests r WHERE r.teacher_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM teachers.teachers t WHERE t.id = r.teacher_id)')->n],
        ];

        $out = [];
        foreach ($checks as $label => [$expected, $actual]) {
            $out[$label] = ['expected' => $expected, 'actual' => $actual, 'ok' => $expected === $actual];
        }

        return $out;
    }

    /**
     * Content, not just counts: an md5 of the ordered business identities of each part of the dataset. Two resets give
     * the same fingerprints when they produced the same data (ids, timestamps and per-run keys are left out on purpose).
     *
     * @return array<string, string>
     */
    private function fingerprints(): array
    {
        $md5 = static fn (string $sql): string => (string) DB::selectOne("SELECT md5(coalesce(string_agg(x, '|' ORDER BY x), '')) AS h FROM ({$sql}) q(x)")->h;

        return [
            'students' => $md5("SELECT concat_ws(',', national_id, full_name, status, gender, birth_date) FROM students.students"),
            'applications' => $md5("SELECT concat_ws(',', national_id, first_name, last_name, status, branch_name, department_name) FROM admission.applications"),
            'teachers' => $md5("SELECT concat_ws(',', employee_code, national_id, full_name, status) FROM teachers.teachers"),
            'enrollments' => $md5("SELECT concat_ws(',', s.national_id, c.code, x.code, e.status, e.effective_from) FROM enrollment.enrollments e JOIN students.students s ON s.id = e.student_id JOIN enrollment.classes c ON c.id = e.class_id JOIN enrollment.sections x ON x.id = e.section_id"),
            'curriculum' => $md5("SELECT concat_ws(',', c.name, s.code, cs.weekly_hours, cs.is_required, cs.status) FROM curriculum.curriculum_subjects cs JOIN curriculum.curricula c ON c.id = cs.curriculum_id JOIN curriculum.subjects s ON s.id = cs.subject_id"),
            'timetable' => $md5("SELECT concat_ws(',', c.code, x.code, t.day_of_week, p.period_number, s.code, tc.employee_code, r.code) FROM timetable.schedules t JOIN enrollment.sections x ON x.id = t.section_id JOIN enrollment.classes c ON c.id = x.class_id JOIN timetable.periods p ON p.id = t.period_id JOIN curriculum.subjects s ON s.id = t.subject_id JOIN teachers.teachers tc ON tc.id = t.teacher_id LEFT JOIN organization.rooms r ON r.id = t.room_id WHERE t.lifecycle_status = 1"),
            'attendance' => $md5("SELECT concat_ws(',', r.attendance_date, st.national_id, r.status, r.minutes_late, s.sheet_status) FROM attendance.records r JOIN attendance.sessions s ON s.id = r.session_id JOIN students.students st ON st.id = r.student_id"),
            'staff_days' => $md5("SELECT concat_ws(',', t.employee_code, a.work_date, a.status) FROM attendance.staff_records a JOIN teachers.teachers t ON t.id = a.teacher_id"),
            'leave' => $md5("SELECT concat_ws(',', t.employee_code, r.date_from, r.date_to, r.status, r.reason) FROM leave.requests r LEFT JOIN teachers.teachers t ON t.id = r.teacher_id"),
            'holidays' => $md5("SELECT concat_ws(',', name, start_date, end_date, status) FROM academic.holidays"),
            'rooms' => $md5("SELECT concat_ws(',', code, name, capacity, status) FROM organization.rooms"),
        ];
    }

    /** @return array{file: string, bytes: int, toc_entries: int} */
    private function backup(string $database): array
    {
        $pgDump = (string) env('SIS_PG_DUMP', 'pg_dump');
        $pgRestore = str_contains($pgDump, DIRECTORY_SEPARATOR) || str_contains($pgDump, '/')
            ? preg_replace('/pg_dump(\.exe)?$/i', 'pg_restore$1', $pgDump)
            : 'pg_restore';
        $connection = config('database.connections.'.config('database.default'));
        $file = storage_path('app/e2e/backups/'.$database.'-'.now()->format('Ymd-His').'.dump');
        File::ensureDirectoryExists(dirname($file));

        $dump = Process::env(['PGPASSWORD' => (string) $connection['password']])->timeout(600)->run([
            $pgDump, '-h', (string) $connection['host'], '-p', (string) $connection['port'], '-U', (string) $connection['username'],
            '-Fc', '-f', $file, $database,
        ]);
        if ($dump->failed()) {
            throw new \RuntimeException('Backup failed (set SIS_PG_DUMP or pass --no-backup): '.trim($dump->errorOutput()));
        }
        $list = Process::timeout(120)->run([$pgRestore, '--list', $file]);
        $entries = $list->successful() ? count(preg_grep('/^\d+;/', explode("\n", $list->output())) ?: []) : 0;
        if ($entries === 0) {
            throw new \RuntimeException('Backup could not be verified with pg_restore --list: '.trim($list->errorOutput()));
        }
        $this->components->twoColumnDetail('Backup', basename($file).' · '.$entries.' TOC entries');

        return ['file' => $file, 'bytes' => (int) filesize($file), 'toc_entries' => $entries];
    }

    private function runSeeder(object $seeder): void
    {
        $seeder->setCommand($this);
        $seeder->setContainer(app());
        $seeder->__invoke();
    }
}
