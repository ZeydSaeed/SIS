<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Application\Attendance\Commands\CancelAttendanceSessionCommand;
use App\Application\Attendance\Commands\CancelAttendanceSessionHandler;
use App\Application\Attendance\Commands\CloseAttendanceSessionCommand;
use App\Application\Attendance\Commands\CloseAttendanceSessionHandler;
use App\Application\Attendance\Commands\CreateAttendanceSessionCommand;
use App\Application\Attendance\Commands\CreateAttendanceSessionHandler;
use App\Application\Attendance\Commands\MarkSectionAttendanceCommand;
use App\Application\Attendance\Commands\MarkSectionAttendanceHandler;
use App\Domain\Attendance\Events\AttendanceSessionCancelled;
use App\Domain\Attendance\Exceptions\SessionCancelConflictException;
use App\Domain\Attendance\Exceptions\SessionCloseConflictException;
use App\Domain\Attendance\Exceptions\SessionNotOpenException;
use App\Domain\Attendance\ValueObjects\AttendanceRecordStatus;
use App\Domain\Attendance\ValueObjects\SessionStatus;
use App\Security\Context\SchoolContext;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class AttendanceCancelSessionPostgreSqlTest extends PostgreSqlIntegrationTestCase
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
     * @return array{school_a:int,year_id:int,section_a:int,subject_id:int,teacher_id:int,period_id:int,student_a:int,enrollment_a:int}
     */
    private function seedMinimal(): array
    {
        $suffix = substr(str_replace('.', '', uniqid('', true)), -4);
        $ministryId = (int) DB::table('organization.ministries')->insertGetId([
            'code' => 'MC'.$suffix, 'name' => 'Min C '.$suffix, 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $dirId = (int) DB::table('organization.directorates')->insertGetId([
            'ministry_id' => $ministryId, 'code' => 'DC'.$suffix, 'name' => 'Dir C '.$suffix, 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $schoolA = (int) DB::table('organization.schools')->insertGetId([
            'directorate_id' => $dirId, 'code' => 'SC'.$suffix, 'name' => 'School C '.$suffix,
            'school_type' => 1, 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $yearId = (int) DB::table('academic.academic_years')->insertGetId([
            'code' => 'YC'.$suffix, 'name' => 'Year C', 'start_date' => '2026-09-01', 'end_date' => '2027-06-30',
            'is_current' => true, 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $gradeId = (int) DB::table('academic.grade_levels')->insertGetId([
            'code' => 'GC'.$suffix, 'name' => 'Grade C', 'level_order' => 10, 'education_stage' => 1, 'status' => 1,
        ]);
        $subjectId = (int) DB::table('curriculum.subjects')->insertGetId([
            'code' => 'SUC'.$suffix, 'name' => 'Subject C', 'subject_type' => 1, 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $teacherId = (int) DB::table('teachers.teachers')->insertGetId([
            'employee_code' => 'TC'.$suffix, 'first_name' => 'T', 'last_name' => 'C', 'full_name' => 'T C',
            'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $periodId = (int) DB::table('timetable.periods')->insertGetId([
            'school_id' => $schoolA, 'period_number' => 1, 'start_time' => '08:00:00',
            'end_time' => '08:45:00', 'period_type' => 1,
        ]);
        $classA = (int) DB::table('enrollment.classes')->insertGetId([
            'school_id' => $schoolA, 'academic_year_id' => $yearId, 'grade_level_id' => $gradeId,
            'code' => 'CC-'.$suffix, 'name' => 'Class C', 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $sectionA = (int) DB::table('enrollment.sections')->insertGetId([
            'class_id' => $classA, 'code' => 'SC', 'name' => 'Section C', 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $studentA = (int) DB::table('students.students')->insertGetId([
            'school_id' => $schoolA, 'student_code' => 'C'.$suffix, 'first_name' => 'C', 'last_name' => 'Stu',
            'full_name' => 'C Stu', 'gender' => 1, 'birth_date' => '2012-01-01', 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $enrollmentA = (int) DB::table('enrollment.enrollments')->insertGetId([
            'student_id' => $studentA, 'academic_year_id' => $yearId, 'school_id' => $schoolA,
            'class_id' => $classA, 'section_id' => $sectionA, 'enrollment_number' => 'EC'.$suffix,
            'status' => 1, 'effective_from' => '2026-09-01', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return [
            'school_a' => $schoolA,
            'year_id' => $yearId,
            'section_a' => $sectionA,
            'subject_id' => $subjectId,
            'teacher_id' => $teacherId,
            'period_id' => $periodId,
            'student_a' => $studentA,
            'enrollment_a' => $enrollmentA,
        ];
    }

    #[Test]
    public function cancel_preserves_records_and_blocks_mark(): void
    {
        $g = $this->seedMinimal();
        $this->bindSchool($g['school_a']);

        $sessionId = (int) $this->app->make(CreateAttendanceSessionHandler::class)->handle(
            new CreateAttendanceSessionCommand(
                schoolId: $g['school_a'],
                academicYearId: $g['year_id'],
                sectionId: $g['section_a'],
                subjectId: $g['subject_id'],
                sessionDate: '2026-10-15',
                teacherId: $g['teacher_id'],
                periodId: $g['period_id'],
            ),
        )->sessionId;

        $this->app->make(MarkSectionAttendanceHandler::class)->handle(new MarkSectionAttendanceCommand(
            sessionId: $sessionId,
            schoolId: $g['school_a'],
            academicYearId: $g['year_id'],
            records: [[
                'studentId' => $g['student_a'],
                'enrollmentId' => $g['enrollment_a'],
                'status' => AttendanceRecordStatus::Present->value,
            ]],
            recordedBy: null,
            idempotencyKey: 'cancel-pg-mark',
        ));

        $recordsBefore = (int) DB::table('attendance.records')->where('session_id', $sessionId)->count();
        $summaryBefore = (int) DB::table('attendance.daily_section_summary')
            ->where('section_id', $g['section_a'])
            ->where('attendance_date', '2026-10-15')
            ->value('present_count');

        $cancel = $this->app->make(CancelAttendanceSessionHandler::class)->handle(
            new CancelAttendanceSessionCommand(
                sessionId: $sessionId,
                schoolId: $g['school_a'],
                reason: 'Void after mark',
                cancelledBy: 1,
                idempotencyKey: 'cancel-pg-1',
            ),
        );

        $this->assertSame(SessionStatus::Open->value, $cancel->previousStatus);
        $this->assertSame(SessionStatus::Cancelled->value, $cancel->newStatus);
        $this->assertSame(SessionStatus::Cancelled->value, (int) DB::table('attendance.sessions')->where('id', $sessionId)->value('status'));
        $this->assertSame($recordsBefore, (int) DB::table('attendance.records')->where('session_id', $sessionId)->count());
        $this->assertSame($summaryBefore, (int) DB::table('attendance.daily_section_summary')
            ->where('section_id', $g['section_a'])
            ->where('attendance_date', '2026-10-15')
            ->value('present_count'));

        $this->assertSame(1, (int) DB::table('audit.outbox_messages')
            ->where('event_type', AttendanceSessionCancelled::class)
            ->where('payload->session_id', $sessionId)
            ->count());

        $this->expectException(SessionNotOpenException::class);
        $this->app->make(MarkSectionAttendanceHandler::class)->handle(new MarkSectionAttendanceCommand(
            sessionId: $sessionId,
            schoolId: $g['school_a'],
            academicYearId: $g['year_id'],
            records: [[
                'studentId' => $g['student_a'],
                'enrollmentId' => $g['enrollment_a'],
                'status' => AttendanceRecordStatus::Absent->value,
            ]],
            recordedBy: null,
            idempotencyKey: 'cancel-pg-mark-2',
        ));
    }

    #[Test]
    public function close_then_cancel_and_close_vs_cancel_race_shape(): void
    {
        $g = $this->seedMinimal();
        $this->bindSchool($g['school_a']);

        $sessionId = (int) $this->app->make(CreateAttendanceSessionHandler::class)->handle(
            new CreateAttendanceSessionCommand(
                schoolId: $g['school_a'],
                academicYearId: $g['year_id'],
                sectionId: $g['section_a'],
                subjectId: $g['subject_id'],
                sessionDate: '2026-10-16',
                teacherId: $g['teacher_id'],
                periodId: $g['period_id'],
                idempotencyKey: 'cancel-pg-create-2',
            ),
        )->sessionId;

        $this->app->make(CloseAttendanceSessionHandler::class)->handle(
            new CloseAttendanceSessionCommand($sessionId, $g['school_a'], 1, 'cancel-pg-close'),
        );

        $cancel = $this->app->make(CancelAttendanceSessionHandler::class)->handle(
            new CancelAttendanceSessionCommand(
                $sessionId, $g['school_a'], 'Void closed', 1, 'cancel-pg-after-close',
            ),
        );
        $this->assertSame(SessionStatus::Closed->value, $cancel->previousStatus);

        $this->expectException(SessionCancelConflictException::class);
        $this->app->make(CancelAttendanceSessionHandler::class)->handle(
            new CancelAttendanceSessionCommand(
                $sessionId, $g['school_a'], 'Again', 1, 'cancel-pg-again',
            ),
        );
    }

    #[Test]
    public function cancel_then_close_conflicts_and_close_then_cancel_succeeds(): void
    {
        $g = $this->seedMinimal();
        $this->bindSchool($g['school_a']);
        $create = $this->app->make(CreateAttendanceSessionHandler::class);

        $openId = (int) $create->handle(new CreateAttendanceSessionCommand(
            schoolId: $g['school_a'],
            academicYearId: $g['year_id'],
            sectionId: $g['section_a'],
            subjectId: $g['subject_id'],
            sessionDate: '2026-10-18',
            teacherId: $g['teacher_id'],
            periodId: $g['period_id'],
            idempotencyKey: 'cancel-race-create-1',
        ))->sessionId;

        $this->app->make(CancelAttendanceSessionHandler::class)->handle(
            new CancelAttendanceSessionCommand($openId, $g['school_a'], 'Cancel wins', 1, 'cancel-race-1'),
        );

        try {
            $this->app->make(CloseAttendanceSessionHandler::class)->handle(
                new CloseAttendanceSessionCommand($openId, $g['school_a'], 1, 'cancel-race-close-fail'),
            );
            $this->fail('Expected SessionCloseConflictException after cancel');
        } catch (SessionCloseConflictException) {
            // expected — cancel won; close cannot succeed on CANCELLED
        }

        $closedId = (int) $create->handle(new CreateAttendanceSessionCommand(
            schoolId: $g['school_a'],
            academicYearId: $g['year_id'],
            sectionId: $g['section_a'],
            subjectId: $g['subject_id'],
            sessionDate: '2026-10-19',
            teacherId: $g['teacher_id'],
            periodId: $g['period_id'],
            idempotencyKey: 'cancel-race-create-2',
        ))->sessionId;

        $this->app->make(CloseAttendanceSessionHandler::class)->handle(
            new CloseAttendanceSessionCommand($closedId, $g['school_a'], 1, 'cancel-race-close-first'),
        );

        $cancel = $this->app->make(CancelAttendanceSessionHandler::class)->handle(
            new CancelAttendanceSessionCommand($closedId, $g['school_a'], 'Cancel after close', 1, 'cancel-race-2'),
        );
        $this->assertSame(SessionStatus::Closed->value, $cancel->previousStatus);
        $this->assertSame(SessionStatus::Cancelled->value, $cancel->newStatus);
    }

    #[Test]
    public function cancelled_then_new_open_same_natural_key_allowed(): void
    {
        $g = $this->seedMinimal();
        $this->bindSchool($g['school_a']);
        $create = $this->app->make(CreateAttendanceSessionHandler::class);

        $firstId = (int) $create->handle(new CreateAttendanceSessionCommand(
            schoolId: $g['school_a'],
            academicYearId: $g['year_id'],
            sectionId: $g['section_a'],
            subjectId: $g['subject_id'],
            sessionDate: '2026-10-17',
            teacherId: $g['teacher_id'],
            periodId: $g['period_id'],
            idempotencyKey: 'cancel-pg-nk-1',
        ))->sessionId;

        $this->app->make(CancelAttendanceSessionHandler::class)->handle(
            new CancelAttendanceSessionCommand($firstId, $g['school_a'], 'Recreate', 1, 'cancel-pg-nk-c'),
        );

        $secondId = (int) $create->handle(new CreateAttendanceSessionCommand(
            schoolId: $g['school_a'],
            academicYearId: $g['year_id'],
            sectionId: $g['section_a'],
            subjectId: $g['subject_id'],
            sessionDate: '2026-10-17',
            teacherId: $g['teacher_id'],
            periodId: $g['period_id'],
            idempotencyKey: 'cancel-pg-nk-2',
        ))->sessionId;

        $this->assertNotSame($firstId, $secondId);
        $this->assertSame(SessionStatus::Open->value, (int) DB::table('attendance.sessions')->where('id', $secondId)->value('status'));
    }
}
