<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Application\Attendance\Commands\CloseAttendanceSessionCommand;
use App\Application\Attendance\Commands\CloseAttendanceSessionHandler;
use App\Application\Attendance\Commands\CorrectAttendanceRecordCommand;
use App\Application\Attendance\Commands\CorrectAttendanceRecordHandler;
use App\Application\Attendance\Commands\CreateAttendanceSessionCommand;
use App\Application\Attendance\Commands\CreateAttendanceSessionHandler;
use App\Application\Attendance\Commands\MarkSectionAttendanceCommand;
use App\Application\Attendance\Commands\MarkSectionAttendanceHandler;
use App\Application\Attendance\Queries\GetAttendanceSessionHandler;
use App\Application\Attendance\Queries\GetAttendanceSessionQuery;
use App\Application\Attendance\Queries\GetDailySectionSummaryHandler;
use App\Application\Attendance\Queries\GetDailySectionSummaryQuery;
use App\Application\Attendance\Queries\GetSectionAttendanceHandler;
use App\Application\Attendance\Queries\GetSectionAttendanceQuery;
use App\Application\Attendance\Queries\GetStudentAttendanceHandler;
use App\Application\Attendance\Queries\GetStudentAttendanceQuery;
use App\Application\Attendance\Queries\ListAttendanceSessionsHandler;
use App\Application\Attendance\Queries\ListAttendanceSessionsQuery;
use App\Domain\Attendance\Exceptions\CrossSchoolAttendanceAccessException;
use App\Domain\Attendance\Exceptions\SessionCloseConflictException;
use App\Domain\Attendance\Exceptions\SessionNotFoundException;
use App\Domain\Attendance\Exceptions\SessionNotOpenException;
use App\Domain\Attendance\ValueObjects\AttendanceRecordStatus;
use App\Domain\Attendance\ValueObjects\SessionStatus;
use App\Security\Context\SchoolContext;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class AttendanceWritePathPostgreSqlTest extends PostgreSqlIntegrationTestCase
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

    /**
     * @return array{
     *   school_a:int,school_b:int,year_id:int,section_a:int,section_b:int,
     *   subject_id:int,teacher_id:int,period_id:int,student_a:int,enrollment_a:int,
     *   student_b:int,enrollment_b:int,class_a:int
     * }
     */
    private function seedGraph(): array
    {
        $suffix = substr(str_replace('.', '', uniqid('', true)), -4);

        $ministryId = (int) DB::table('organization.ministries')->insertGetId([
            'code' => 'MA'.$suffix,
            'name' => 'Ministry Att '.$suffix,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $dirId = (int) DB::table('organization.directorates')->insertGetId([
            'ministry_id' => $ministryId,
            'code' => 'DA'.$suffix,
            'name' => 'Dir Att '.$suffix,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $schoolA = (int) DB::table('organization.schools')->insertGetId([
            'directorate_id' => $dirId,
            'code' => 'SA'.$suffix,
            'name' => 'School A '.$suffix,
            'school_type' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $schoolB = (int) DB::table('organization.schools')->insertGetId([
            'directorate_id' => $dirId,
            'code' => 'SB'.$suffix,
            'name' => 'School B '.$suffix,
            'school_type' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $yearId = (int) DB::table('academic.academic_years')->insertGetId([
            'code' => 'YA'.$suffix,
            'name' => 'Attendance Year',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
            'is_current' => true,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $gradeId = (int) DB::table('academic.grade_levels')->insertGetId([
            'code' => 'GA'.$suffix,
            'name' => 'Grade Att',
            'level_order' => 10,
            'education_stage' => 1,
            'status' => 1,
        ]);

        $subjectId = (int) DB::table('curriculum.subjects')->insertGetId([
            'code' => 'SU'.$suffix,
            'name' => 'Subject Att',
            'subject_type' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $teacherId = (int) DB::table('teachers.teachers')->insertGetId([
            'employee_code' => 'TA'.$suffix,
            'first_name' => 'Teach',
            'last_name' => 'Er',
            'full_name' => 'Teach Er',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $periodId = (int) DB::table('timetable.periods')->insertGetId([
            'school_id' => $schoolA,
            'period_number' => 1,
            'start_time' => '08:00:00',
            'end_time' => '08:45:00',
            'period_type' => 1,
        ]);

        $classA = (int) DB::table('enrollment.classes')->insertGetId([
            'school_id' => $schoolA,
            'academic_year_id' => $yearId,
            'grade_level_id' => $gradeId,
            'code' => 'CA-'.$suffix,
            'name' => 'Class A',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $classB = (int) DB::table('enrollment.classes')->insertGetId([
            'school_id' => $schoolB,
            'academic_year_id' => $yearId,
            'grade_level_id' => $gradeId,
            'code' => 'CB-'.$suffix,
            'name' => 'Class B',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sectionA = (int) DB::table('enrollment.sections')->insertGetId([
            'class_id' => $classA,
            'code' => 'SA',
            'name' => 'Section A',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $sectionB = (int) DB::table('enrollment.sections')->insertGetId([
            'class_id' => $classB,
            'code' => 'SB',
            'name' => 'Section B',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $studentA = (int) DB::table('students.students')->insertGetId([
            'school_id' => $schoolA,
            'student_code' => 'A'.$suffix,
            'first_name' => 'Ali',
            'last_name' => 'A',
            'full_name' => 'Ali A',
            'gender' => 1,
            'birth_date' => '2010-01-01',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $studentB = (int) DB::table('students.students')->insertGetId([
            'school_id' => $schoolA,
            'student_code' => 'B'.$suffix,
            'first_name' => 'Bara',
            'last_name' => 'B',
            'full_name' => 'Bara B',
            'gender' => 1,
            'birth_date' => '2010-02-01',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $enrollmentA = (int) DB::table('enrollment.enrollments')->insertGetId([
            'student_id' => $studentA,
            'academic_year_id' => $yearId,
            'school_id' => $schoolA,
            'class_id' => $classA,
            'section_id' => $sectionA,
            'enrollment_number' => 'EA'.$suffix,
            'status' => 1,
            'effective_from' => '2026-09-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $enrollmentB = (int) DB::table('enrollment.enrollments')->insertGetId([
            'student_id' => $studentB,
            'academic_year_id' => $yearId,
            'school_id' => $schoolA,
            'class_id' => $classA,
            'section_id' => $sectionA,
            'enrollment_number' => 'EB'.$suffix,
            'status' => 1,
            'effective_from' => '2026-09-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'school_a' => $schoolA,
            'school_b' => $schoolB,
            'year_id' => $yearId,
            'section_a' => $sectionA,
            'section_b' => $sectionB,
            'subject_id' => $subjectId,
            'teacher_id' => $teacherId,
            'period_id' => $periodId,
            'student_a' => $studentA,
            'enrollment_a' => $enrollmentA,
            'student_b' => $studentB,
            'enrollment_b' => $enrollmentB,
            'class_a' => $classA,
        ];
    }

    #[Test]
    public function create_mark_correct_close_queries_and_idempotency(): void
    {
        $g = $this->seedGraph();
        $this->bindSchool($g['school_a']);

        $create = $this->app->make(CreateAttendanceSessionHandler::class);
        $created = $create->handle(new CreateAttendanceSessionCommand(
            schoolId: $g['school_a'],
            academicYearId: $g['year_id'],
            sectionId: $g['section_a'],
            subjectId: $g['subject_id'],
            sessionDate: '2026-10-05',
            teacherId: $g['teacher_id'],
            periodId: $g['period_id'],
            createdBy: null,
            idempotencyKey: 'att-create-1',
        ));
        $replayCreate = $create->handle(new CreateAttendanceSessionCommand(
            schoolId: $g['school_a'],
            academicYearId: $g['year_id'],
            sectionId: $g['section_a'],
            subjectId: $g['subject_id'],
            sessionDate: '2026-10-05',
            teacherId: $g['teacher_id'],
            periodId: $g['period_id'],
            createdBy: null,
            idempotencyKey: 'att-create-1',
        ));

        $this->assertFalse($created->fromIdempotencyCache);
        $this->assertTrue($replayCreate->fromIdempotencyCache);
        $this->assertSame($created->sessionId, $replayCreate->sessionId);
        $sessionId = (int) $created->sessionId;

        $mark = $this->app->make(MarkSectionAttendanceHandler::class);
        $marked = $mark->handle(new MarkSectionAttendanceCommand(
            sessionId: $sessionId,
            schoolId: $g['school_a'],
            academicYearId: $g['year_id'],
            records: [
                [
                    'studentId' => $g['student_a'],
                    'enrollmentId' => $g['enrollment_a'],
                    'status' => AttendanceRecordStatus::Present->value,
                ],
                [
                    'studentId' => $g['student_b'],
                    'enrollmentId' => $g['enrollment_b'],
                    'status' => AttendanceRecordStatus::Absent->value,
                    'notes' => 'sick',
                ],
            ],
            recordedBy: null,
            idempotencyKey: 'att-mark-1',
        ));
        $markReplay = $mark->handle(new MarkSectionAttendanceCommand(
            sessionId: $sessionId,
            schoolId: $g['school_a'],
            academicYearId: $g['year_id'],
            records: [
                [
                    'studentId' => $g['student_a'],
                    'enrollmentId' => $g['enrollment_a'],
                    'status' => AttendanceRecordStatus::Present->value,
                ],
            ],
            recordedBy: null,
            idempotencyKey: 'att-mark-1',
        ));

        $this->assertSame(2, $marked->markedCount);
        $this->assertTrue($markReplay->fromIdempotencyCache);
        $this->assertSame(2, (int) DB::table('attendance.records')->where('session_id', $sessionId)->count());

        $summary = DB::table('attendance.daily_section_summary')
            ->where('section_id', $g['section_a'])
            ->where('attendance_date', '2026-10-05')
            ->first();
        $this->assertNotNull($summary);
        $this->assertSame(2, (int) $summary->total_students);
        $this->assertSame(1, (int) $summary->present_count);
        $this->assertSame(1, (int) $summary->absent_count);

        $correct = $this->app->make(CorrectAttendanceRecordHandler::class);
        $corrected = $correct->handle(new CorrectAttendanceRecordCommand(
            sessionId: $sessionId,
            studentId: $g['student_b'],
            academicYearId: $g['year_id'],
            schoolId: $g['school_a'],
            newStatus: AttendanceRecordStatus::Late->value,
            newNotes: 'arrived late',
            reason: 'mis-marked',
            recordedBy: null,
            idempotencyKey: 'att-correct-1',
        ));
        $this->assertSame(AttendanceRecordStatus::Absent->value, $corrected->previousStatus);
        $this->assertSame(AttendanceRecordStatus::Late->value, $corrected->newStatus);

        $this->assertSame(1, (int) DB::table('audit.outbox_messages')
            ->where('event_type', 'like', '%AttendanceCorrected%')
            ->count());

        $sessionDto = $this->app->make(GetAttendanceSessionHandler::class)
            ->handle(new GetAttendanceSessionQuery($g['school_a'], $sessionId, true));
        $this->assertSame($sessionId, $sessionDto->id);
        $this->assertCount(2, $sessionDto->records ?? []);

        $list = $this->app->make(ListAttendanceSessionsHandler::class)
            ->handle(new ListAttendanceSessionsQuery($g['school_a'], $g['year_id']));
        $this->assertGreaterThanOrEqual(1, $list->pagination['total']);

        $sectionAtt = $this->app->make(GetSectionAttendanceHandler::class)
            ->handle(new GetSectionAttendanceQuery($g['school_a'], $g['section_a'], '2026-10-05', $g['year_id']));
        $this->assertCount(1, $sectionAtt->sessions);
        $this->assertCount(2, $sectionAtt->records);

        $studentAtt = $this->app->make(GetStudentAttendanceHandler::class)
            ->handle(new GetStudentAttendanceQuery($g['school_a'], $g['student_a'], $g['year_id']));
        $this->assertSame(1, $studentAtt->pagination['total']);

        $daily = $this->app->make(GetDailySectionSummaryHandler::class)
            ->handle(new GetDailySectionSummaryQuery($g['school_a'], $g['section_a'], '2026-10-05'));
        $this->assertCount(1, $daily);
        $this->assertSame(1, $daily[0]->lateCount);

        $close = $this->app->make(CloseAttendanceSessionHandler::class);
        $closed = $close->handle(new CloseAttendanceSessionCommand($sessionId, $g['school_a'], null, 'att-close-1'));
        $closeReplay = $close->handle(new CloseAttendanceSessionCommand($sessionId, $g['school_a'], null, 'att-close-1'));
        $this->assertFalse($closed->fromIdempotencyCache);
        $this->assertTrue($closeReplay->fromIdempotencyCache);
        $this->assertSame(SessionStatus::Closed->value, (int) DB::table('attendance.sessions')->where('id', $sessionId)->value('status'));

        $this->expectException(SessionNotOpenException::class);
        $mark->handle(new MarkSectionAttendanceCommand(
            sessionId: $sessionId,
            schoolId: $g['school_a'],
            academicYearId: $g['year_id'],
            records: [[
                'studentId' => $g['student_a'],
                'enrollmentId' => $g['enrollment_a'],
                'status' => AttendanceRecordStatus::Present->value,
            ]],
            recordedBy: null,
            idempotencyKey: 'att-mark-after-close',
        ));
    }

    #[Test]
    public function cross_school_access_is_denied_and_close_conflict_fails(): void
    {
        $g = $this->seedGraph();
        $this->bindSchool($g['school_a']);

        $sessionId = (int) $this->app->make(CreateAttendanceSessionHandler::class)->handle(
            new CreateAttendanceSessionCommand(
                $g['school_a'],
                $g['year_id'],
                $g['section_a'],
                $g['subject_id'],
                '2026-10-06',
                $g['teacher_id'],
                null,
                null,
                'att-create-x',
            ),
        )->sessionId;

        $this->bindSchool($g['school_b']);
        $this->expectException(CrossSchoolAttendanceAccessException::class);
        $this->app->make(MarkSectionAttendanceHandler::class)->handle(new MarkSectionAttendanceCommand(
            $sessionId,
            $g['school_b'],
            $g['year_id'],
            [[
                'studentId' => $g['student_a'],
                'enrollmentId' => $g['enrollment_a'],
                'status' => 1,
            ]],
            null,
            'att-mark-cross',
        ));
    }

    #[Test]
    public function get_session_cross_school_not_found_and_second_close_conflicts(): void
    {
        $g = $this->seedGraph();
        $this->bindSchool($g['school_a']);

        $sessionId = (int) $this->app->make(CreateAttendanceSessionHandler::class)->handle(
            new CreateAttendanceSessionCommand(
                $g['school_a'],
                $g['year_id'],
                $g['section_a'],
                $g['subject_id'],
                '2026-10-07',
                $g['teacher_id'],
                null,
                null,
                'att-create-close',
            ),
        )->sessionId;

        $this->app->make(CloseAttendanceSessionHandler::class)->handle(
            new CloseAttendanceSessionCommand($sessionId, $g['school_a'], null, 'att-close-ok'),
        );

        try {
            $this->app->make(CloseAttendanceSessionHandler::class)->handle(
                new CloseAttendanceSessionCommand($sessionId, $g['school_a'], null, 'att-close-conflict'),
            );
            $this->fail('Expected SessionCloseConflictException');
        } catch (SessionCloseConflictException) {
            // expected
        }

        $this->expectException(SessionNotFoundException::class);
        $this->app->make(GetAttendanceSessionHandler::class)->handle(
            new GetAttendanceSessionQuery($g['school_b'], $sessionId),
        );
    }
}
