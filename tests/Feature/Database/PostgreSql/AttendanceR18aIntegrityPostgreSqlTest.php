<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Application\Attendance\Commands\CreateAttendanceSessionCommand;
use App\Application\Attendance\Commands\CreateAttendanceSessionHandler;
use App\Domain\Attendance\Exceptions\DuplicateOpenAttendanceSessionException;
use App\Domain\Attendance\ValueObjects\SessionStatus;
use App\Security\Context\SchoolContext;
use Illuminate\Support\Facades\DB;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

/**
 * R1.8A — status CHECK + OPEN-session partial UNIQUE (NULLS NOT DISTINCT).
 */
final class AttendanceR18aIntegrityPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    protected function connectionsToTransact(): array
    {
        return [];
    }

    private function bindSchool(int $schoolId): void
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, false)", [(string) $schoolId]);
        $this->app->make(SchoolContext::class)->set($schoolId);
    }

    private function openPgsqlPdo(): PDO
    {
        $cfg = config('database.connections.pgsql');
        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            $cfg['host'],
            $cfg['port'],
            $cfg['database'],
        );
        $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $searchPath = (string) ($cfg['search_path'] ?? 'public');
        $pdo->exec('SET search_path TO '.$searchPath);

        return $pdo;
    }

    private function setSchoolContext(PDO $pdo, int $schoolId): void
    {
        $stmt = $pdo->prepare("SELECT set_config('app.current_school_id', ?, false)");
        $stmt->execute([(string) $schoolId]);
    }

    /**
     * @return array{
     *   school_a:int,school_b:int,year_a:int,year_b:int,section_a:int,section_b:int,
     *   subject_id:int,teacher_id:int,period_1:int,period_2:int
     * }
     */
    private function seedGraph(): array
    {
        $suffix = substr(str_replace('.', '', uniqid('', true)), -4);

        $ministryId = (int) DB::table('organization.ministries')->insertGetId([
            'code' => 'M8'.$suffix,
            'name' => 'Ministry R18a '.$suffix,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $dirId = (int) DB::table('organization.directorates')->insertGetId([
            'ministry_id' => $ministryId,
            'code' => 'D8'.$suffix,
            'name' => 'Dir R18a '.$suffix,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $schoolA = (int) DB::table('organization.schools')->insertGetId([
            'directorate_id' => $dirId,
            'code' => 'S8A'.$suffix,
            'name' => 'School A R18a '.$suffix,
            'school_type' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $schoolB = (int) DB::table('organization.schools')->insertGetId([
            'directorate_id' => $dirId,
            'code' => 'S8B'.$suffix,
            'name' => 'School B R18a '.$suffix,
            'school_type' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $yearA = (int) DB::table('academic.academic_years')->insertGetId([
            'code' => 'Y8A'.$suffix,
            'name' => 'Year A R18a',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
            'is_current' => true,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $yearB = (int) DB::table('academic.academic_years')->insertGetId([
            'code' => 'Y8B'.$suffix,
            'name' => 'Year B R18a',
            'start_date' => '2025-09-01',
            'end_date' => '2026-06-30',
            'is_current' => false,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $gradeId = (int) DB::table('academic.grade_levels')->insertGetId([
            'code' => 'G8'.$suffix,
            'name' => 'Grade R18a',
            'level_order' => 10,
            'education_stage' => 1,
            'status' => 1,
        ]);
        $subjectId = (int) DB::table('curriculum.subjects')->insertGetId([
            'code' => 'SU8'.$suffix,
            'name' => 'Subject R18a',
            'subject_type' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $teacherId = (int) DB::table('teachers.teachers')->insertGetId([
            'employee_code' => 'T8'.$suffix,
            'first_name' => 'Teach',
            'last_name' => 'R18a',
            'full_name' => 'Teach R18a',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $period1 = (int) DB::table('timetable.periods')->insertGetId([
            'school_id' => $schoolA,
            'period_number' => 1,
            'start_time' => '08:00:00',
            'end_time' => '08:45:00',
            'period_type' => 1,
        ]);
        $period2 = (int) DB::table('timetable.periods')->insertGetId([
            'school_id' => $schoolA,
            'period_number' => 2,
            'start_time' => '09:00:00',
            'end_time' => '09:45:00',
            'period_type' => 1,
        ]);

        $classA = (int) DB::table('enrollment.classes')->insertGetId([
            'school_id' => $schoolA,
            'academic_year_id' => $yearA,
            'grade_level_id' => $gradeId,
            'code' => 'C8A-'.$suffix,
            'name' => 'Class A',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $classB = (int) DB::table('enrollment.classes')->insertGetId([
            'school_id' => $schoolB,
            'academic_year_id' => $yearA,
            'grade_level_id' => $gradeId,
            'code' => 'C8B-'.$suffix,
            'name' => 'Class B',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $sectionA = (int) DB::table('enrollment.sections')->insertGetId([
            'class_id' => $classA,
            'code' => 'S8A',
            'name' => 'Section A',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $sectionB = (int) DB::table('enrollment.sections')->insertGetId([
            'class_id' => $classB,
            'code' => 'S8B',
            'name' => 'Section B',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'school_a' => $schoolA,
            'school_b' => $schoolB,
            'year_a' => $yearA,
            'year_b' => $yearB,
            'section_a' => $sectionA,
            'section_b' => $sectionB,
            'subject_id' => $subjectId,
            'teacher_id' => $teacherId,
            'period_1' => $period1,
            'period_2' => $period2,
        ];
    }

    /**
     * @param  array{school_a:int,year_a:int,section_a:int,subject_id:int,teacher_id:int}  $g
     */
    private function insertSession(
        array $g,
        int $status,
        ?int $periodId,
        ?int $schoolId = null,
        ?int $yearId = null,
        ?int $sectionId = null,
        string $date = '2026-10-12',
    ): int {
        $school = $schoolId ?? $g['school_a'];
        $this->bindSchool($school);

        return (int) DB::table('attendance.sessions')->insertGetId([
            'school_id' => $school,
            'section_id' => $sectionId ?? $g['section_a'],
            'subject_id' => $g['subject_id'],
            'academic_year_id' => $yearId ?? $g['year_a'],
            'session_date' => $date,
            'period_id' => $periodId,
            'teacher_id' => $g['teacher_id'],
            'status' => $status,
            'created_at' => now(),
        ]);
    }

    #[Test]
    public function status_check_accepts_open_closed_cancelled_and_rejects_invalid(): void
    {
        $g = $this->seedGraph();

        $openId = $this->insertSession($g, SessionStatus::Open->value, $g['period_1'], date: '2026-10-01');
        $closedId = $this->insertSession($g, SessionStatus::Closed->value, $g['period_1'], date: '2026-10-02');
        $cancelledId = $this->insertSession($g, SessionStatus::Cancelled->value, $g['period_1'], date: '2026-10-03');

        $this->assertGreaterThan(0, $openId);
        $this->assertGreaterThan(0, $closedId);
        $this->assertGreaterThan(0, $cancelledId);

        $this->bindSchool($g['school_a']);
        $rejected = false;
        try {
            DB::table('attendance.sessions')->insert([
                'school_id' => $g['school_a'],
                'section_id' => $g['section_a'],
                'subject_id' => $g['subject_id'],
                'academic_year_id' => $g['year_a'],
                'session_date' => '2026-10-04',
                'period_id' => $g['period_1'],
                'teacher_id' => $g['teacher_id'],
                'status' => 9,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $rejected = true;
            $this->assertStringContainsString('attendance_sessions_status_check', $e->getMessage());
        }
        $this->assertTrue($rejected, 'Invalid status must be rejected by CHECK');

        $check = DB::selectOne("
            SELECT 1 AS ok
            FROM pg_constraint
            WHERE conname = 'attendance_sessions_status_check'
              AND conrelid = 'attendance.sessions'::regclass
        ");
        $this->assertNotNull($check);

        $idx = DB::selectOne("
            SELECT 1 AS ok
            FROM pg_indexes
            WHERE schemaname = 'attendance'
              AND indexname = 'attendance_sessions_open_natural_key_uidx'
        ");
        $this->assertNotNull($idx);
    }

    #[Test]
    public function sequential_duplicate_open_is_rejected_by_db_and_handler(): void
    {
        $g = $this->seedGraph();
        $this->bindSchool($g['school_a']);

        $handler = $this->app->make(CreateAttendanceSessionHandler::class);
        $first = $handler->handle(new CreateAttendanceSessionCommand(
            schoolId: $g['school_a'],
            academicYearId: $g['year_a'],
            sectionId: $g['section_a'],
            subjectId: $g['subject_id'],
            sessionDate: '2026-10-12',
            teacherId: $g['teacher_id'],
            periodId: $g['period_1'],
        ));
        $this->assertNotNull($first->sessionId);

        $this->expectException(DuplicateOpenAttendanceSessionException::class);
        $handler->handle(new CreateAttendanceSessionCommand(
            schoolId: $g['school_a'],
            academicYearId: $g['year_a'],
            sectionId: $g['section_a'],
            subjectId: $g['subject_id'],
            sessionDate: '2026-10-12',
            teacherId: $g['teacher_id'],
            periodId: $g['period_1'],
            idempotencyKey: 'r18a-dup-2',
        ));
    }

    #[Test]
    public function concurrent_duplicate_open_cannot_produce_two_open_rows(): void
    {
        $g = $this->seedGraph();
        $date = '2026-10-20';

        $pdoA = $this->openPgsqlPdo();
        $pdoB = $this->openPgsqlPdo();
        $this->setSchoolContext($pdoA, $g['school_a']);
        $this->setSchoolContext($pdoB, $g['school_a']);

        $sql = 'INSERT INTO attendance.sessions
            (school_id, section_id, subject_id, academic_year_id, session_date, period_id, teacher_id, status, created_at)
            VALUES (?, ?, ?, ?, ?::date, ?, ?, 1, NOW())';

        $params = [
            $g['school_a'],
            $g['section_a'],
            $g['subject_id'],
            $g['year_a'],
            $date,
            $g['period_1'],
            $g['teacher_id'],
        ];

        $pdoA->beginTransaction();
        $pdoB->beginTransaction();

        $stmtA = $pdoA->prepare($sql);
        $stmtA->execute($params);

        $errorCode = null;
        $stmtB = $pdoB->prepare($sql);
        try {
            $pdoA->commit();
            $stmtB->execute($params);
            $pdoB->commit();
        } catch (PDOException $e) {
            $errorCode = (string) $e->getCode();
            if ($pdoB->inTransaction()) {
                $pdoB->rollBack();
            }
            if ($pdoA->inTransaction()) {
                $pdoA->commit();
            }
        }

        $this->assertSame('23505', $errorCode, 'Expected unique_violation on concurrent duplicate OPEN');

        $this->bindSchool($g['school_a']);
        $count = (int) DB::table('attendance.sessions')
            ->where('school_id', $g['school_a'])
            ->where('academic_year_id', $g['year_a'])
            ->where('section_id', $g['section_a'])
            ->where('subject_id', $g['subject_id'])
            ->where('session_date', $date)
            ->where('period_id', $g['period_1'])
            ->where('status', SessionStatus::Open->value)
            ->count();
        $this->assertSame(1, $count);
    }

    #[Test]
    public function closed_session_allows_new_open_with_same_natural_key(): void
    {
        $g = $this->seedGraph();
        $closedId = $this->insertSession($g, SessionStatus::Closed->value, $g['period_1']);
        $this->assertGreaterThan(0, $closedId);

        $this->bindSchool($g['school_a']);
        $handler = $this->app->make(CreateAttendanceSessionHandler::class);
        $open = $handler->handle(new CreateAttendanceSessionCommand(
            schoolId: $g['school_a'],
            academicYearId: $g['year_a'],
            sectionId: $g['section_a'],
            subjectId: $g['subject_id'],
            sessionDate: '2026-10-12',
            teacherId: $g['teacher_id'],
            periodId: $g['period_1'],
        ));

        $this->assertNotNull($open->sessionId);
        $this->assertNotSame($closedId, $open->sessionId);
    }

    #[Test]
    public function different_period_null_vs_period_and_cross_keys_succeed(): void
    {
        $g = $this->seedGraph();

        $p1 = $this->insertSession($g, SessionStatus::Open->value, $g['period_1'], date: '2026-11-01');
        $p2 = $this->insertSession($g, SessionStatus::Open->value, $g['period_2'], date: '2026-11-01');
        $nullBucket = $this->insertSession($g, SessionStatus::Open->value, null, date: '2026-11-01');

        $this->assertGreaterThan(0, $p1);
        $this->assertGreaterThan(0, $p2);
        $this->assertGreaterThan(0, $nullBucket);

        $this->bindSchool($g['school_a']);
        $dupNullRejected = false;
        try {
            $this->insertSession($g, SessionStatus::Open->value, null, date: '2026-11-01');
        } catch (\Throwable) {
            $dupNullRejected = true;
        }
        $this->assertTrue($dupNullRejected, 'NULL+NULL OPEN must be rejected');

        $otherSchool = $this->insertSession(
            $g,
            SessionStatus::Open->value,
            null,
            schoolId: $g['school_b'],
            sectionId: $g['section_b'],
            date: '2026-11-01',
        );
        $this->assertGreaterThan(0, $otherSchool);

        $otherYear = $this->insertSession(
            $g,
            SessionStatus::Open->value,
            $g['period_1'],
            yearId: $g['year_b'],
            date: '2026-05-01',
        );
        $this->assertGreaterThan(0, $otherYear);
    }

    #[Test]
    public function strategy_a_and_rls_remain_enabled_and_forced(): void
    {
        $row = DB::selectOne("
            SELECT c.relrowsecurity AS rls, c.relforcerowsecurity AS force_rls
            FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'attendance' AND c.relname = 'sessions'
        ");
        $this->assertTrue((bool) $row->rls);
        $this->assertTrue((bool) $row->force_rls);
    }
}
