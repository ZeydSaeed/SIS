<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Domain\Exams\ValueObjects\ExamEnrollmentStatus;
use App\Domain\Exams\ValueObjects\ExamSessionStatus;
use App\Domain\Exams\ValueObjects\ExamStatus;
use App\Security\Authorization\Permission;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;
use Tests\Support\Exams\SeedsApplicationExamGradeGraph;

final class PhaseExamHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;
    use SeedsApplicationExamGradeGraph;

    #[Test]
    public function exam_view_permission_registered_on_grades_roles(): void
    {
        $this->assertArrayHasKey(Permission::EXAM_VIEW, config('security.permissions'));
        $this->assertContains(Permission::EXAM_VIEW, config('security.roles.grades_manager'));
        $this->assertContains(Permission::EXAM_VIEW, config('security.roles.grades_viewer'));
        $this->assertContains(Permission::EXAM_VIEW, config('security.roles.grades_teacher'));
    }

    #[Test]
    public function manager_can_create_list_and_show_exam(): void
    {
        $schoolId = $this->createSchool('SCH-EX-HTTP', 'Exam HTTP');
        $yearId = $this->createAcademicYear('AY-EX-HTTP');
        [$termId, $typeId] = $this->seedTermAndType($yearId, 'H1');
        $this->actingAsGradesManagerForSchool($schoolId);

        $create = $this->postJson('/api/v1/exams', [
            'academic_year_id' => $yearId,
            'term_id' => $termId,
            'exam_type_id' => $typeId,
            'name' => 'Midterm Wave4',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-15',
        ], ['X-Idempotency-Key' => 'exam-http-create-1'])
            ->assertCreated()
            ->assertJsonPath('data.status', ExamStatus::Draft->value);

        $examId = (int) $create->json('data.id');

        $this->getJson('/api/v1/exams?academic_year_id='.$yearId)
            ->assertOk()
            ->assertJsonPath('data.0.id', $examId);

        $this->getJson('/api/v1/exams/'.$examId)
            ->assertOk()
            ->assertJsonPath('data.name', 'Midterm Wave4')
            ->assertJsonPath('data.school_id', $schoolId);
    }

    #[Test]
    public function manager_can_create_list_show_open_and_close_session(): void
    {
        $schoolId = $this->createSchool('SCH-EX-SES', 'Exam Session HTTP');
        $this->actingAsGradesManagerForSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'SES');

        $subjectId = (int) DB::table(SchemaHelper::qualified('curriculum', 'subjects'))->insertGetId([
            'code' => 'SUB'.substr(uniqid(), -4),
            'name' => 'Session Subject',
            'subject_type' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $create = $this->postJson('/api/v1/exams/'.$graph['exam_id'].'/sessions', [
            'subject_id' => $subjectId,
            'session_date' => '2026-11-08',
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'max_grade' => 100,
            'pass_grade' => 50,
        ], ['X-Idempotency-Key' => 'exam-http-session-1'])
            ->assertCreated()
            ->assertJsonPath('data.status', ExamSessionStatus::Scheduled->value);

        $sessionId = (int) $create->json('data.id');

        $this->getJson('/api/v1/exams/'.$graph['exam_id'].'/sessions')
            ->assertOk();

        $this->getJson('/api/v1/exam-sessions/'.$sessionId)
            ->assertOk()
            ->assertJsonPath('data.id', $sessionId)
            ->assertJsonPath('data.exam_id', $graph['exam_id']);

        $this->postJson('/api/v1/exam-sessions/'.$sessionId.'/open', [], [
            'X-Idempotency-Key' => 'exam-http-open-1',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', ExamSessionStatus::InProgress->value);

        $this->postJson('/api/v1/exam-sessions/'.$sessionId.'/close', [], [
            'X-Idempotency-Key' => 'exam-http-close-1',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', ExamSessionStatus::Completed->value);
    }

    #[Test]
    public function manager_can_enroll_list_show_cancel_reopen_and_present(): void
    {
        $schoolId = $this->createSchool('SCH-EX-ENR', 'Exam Enroll HTTP');
        $this->actingAsGradesManagerForSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'ENR');

        $secondEnrollment = $this->createActiveEnrollmentForSchool($schoolId, $graph['year_id']);

        $create = $this->postJson('/api/v1/exam-sessions/'.$graph['session_id'].'/enrollments', [
            'enrollment_id' => $secondEnrollment->id,
            'seat_number' => 'B2',
        ], ['X-Idempotency-Key' => 'exam-http-enroll-1'])
            ->assertCreated()
            ->assertJsonPath('data.status', ExamEnrollmentStatus::Registered->value);

        $examEnrollmentId = (int) $create->json('data.id');

        $this->getJson('/api/v1/exam-sessions/'.$graph['session_id'].'/enrollments')
            ->assertOk();

        $this->getJson('/api/v1/exam-enrollments/'.$examEnrollmentId)
            ->assertOk()
            ->assertJsonPath('data.enrollment_id', $secondEnrollment->id)
            ->assertJsonPath('data.seat_number', 'B2');

        $this->postJson('/api/v1/exam-enrollments/'.$examEnrollmentId.'/cancel', [], [
            'X-Idempotency-Key' => 'exam-http-cancel-enr-1',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', ExamEnrollmentStatus::Withdrawn->value);

        $this->postJson('/api/v1/exam-enrollments/'.$examEnrollmentId.'/reopen', [], [
            'X-Idempotency-Key' => 'exam-http-reopen-enr-1',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', ExamEnrollmentStatus::Registered->value);

        DB::table(SchemaHelper::qualified('exams', 'exam_enrollments'))
            ->where('id', $examEnrollmentId)
            ->update(['status' => ExamEnrollmentStatus::Confirmed->value]);

        DB::table(SchemaHelper::qualified('exams', 'exam_sessions'))
            ->where('id', $graph['session_id'])
            ->update(['status' => ExamSessionStatus::InProgress->value]);

        $this->postJson('/api/v1/exam-enrollments/'.$examEnrollmentId.'/present', [], [
            'X-Idempotency-Key' => 'exam-http-present-1',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', ExamEnrollmentStatus::Present->value);
    }

    #[Test]
    public function manager_can_update_and_cancel_exam(): void
    {
        $schoolId = $this->createSchool('SCH-EX-UPD', 'Exam Update HTTP');
        $yearId = $this->createAcademicYear('AY-EX-UPD');
        [$termId, $typeId] = $this->seedTermAndType($yearId, 'U8');
        $this->actingAsGradesManagerForSchool($schoolId);

        $create = $this->postJson('/api/v1/exams', [
            'academic_year_id' => $yearId,
            'term_id' => $termId,
            'exam_type_id' => $typeId,
            'name' => 'Draft Exam Wave5',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-15',
        ], ['X-Idempotency-Key' => 'exam-http-update-create-1'])
            ->assertCreated();

        $examId = (int) $create->json('data.id');

        $this->patchJson('/api/v1/exams/'.$examId, [
            'name' => 'Updated Exam Wave5',
            'target_status' => ExamStatus::Scheduled->value,
        ], ['X-Idempotency-Key' => 'exam-http-update-1'])
            ->assertOk()
            ->assertJsonPath('data.id', $examId)
            ->assertJsonPath('data.status', ExamStatus::Scheduled->value);

        $this->getJson('/api/v1/exams/'.$examId)
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Exam Wave5');

        $this->postJson('/api/v1/exams/'.$examId.'/cancel', [], [
            'X-Idempotency-Key' => 'exam-http-cancel-1',
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $examId)
            ->assertJsonPath('data.status', ExamStatus::Cancelled->value);
    }

    #[Test]
    public function manager_can_update_exam_session_and_enrollment(): void
    {
        $schoolId = $this->createSchool('SCH-EX-U10', 'Exam Session Update HTTP');
        $this->actingAsGradesManagerForSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'U10');

        $this->patchJson('/api/v1/exam-sessions/'.$graph['session_id'], [
            'session_date' => '2026-11-09',
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
            'max_grade' => 120,
            'pass_grade' => 60,
        ], ['X-Idempotency-Key' => 'exam-http-session-update-1'])
            ->assertOk()
            ->assertJsonPath('data.id', $graph['session_id'])
            ->assertJsonPath('data.status', ExamSessionStatus::Scheduled->value);

        $this->getJson('/api/v1/exam-sessions/'.$graph['session_id'])
            ->assertOk()
            ->assertJsonPath('data.session_date', '2026-11-09')
            ->assertJsonPath('data.max_grade', 120);

        $this->patchJson('/api/v1/exam-enrollments/'.$graph['exam_enrollment_id'], [
            'seat_number' => 'C9',
            'status' => ExamEnrollmentStatus::Confirmed->value,
        ], ['X-Idempotency-Key' => 'exam-http-enroll-update-1'])
            ->assertOk()
            ->assertJsonPath('data.id', $graph['exam_enrollment_id'])
            ->assertJsonPath('data.status', ExamEnrollmentStatus::Confirmed->value);

        $this->getJson('/api/v1/exam-enrollments/'.$graph['exam_enrollment_id'])
            ->assertOk()
            ->assertJsonPath('data.seat_number', 'C9')
            ->assertJsonPath('data.status', ExamEnrollmentStatus::Confirmed->value);
    }

    #[Test]
    public function cancel_exam_session_http_route_is_absent(): void
    {
        $routes = collect(app('router')->getRoutes())->map(fn ($r) => $r->uri())->implode(' ');
        $this->assertStringNotContainsString('exam-sessions/{examSession}/cancel', $routes);
    }

    #[Test]
    public function unauthorized_user_cannot_list_exams(): void
    {
        $schoolId = $this->createSchool('SCH-EX-DENY', 'Exam Deny');
        $this->actingAsAttendanceViewer(schoolId: $schoolId);

        $this->getJson('/api/v1/exams')->assertForbidden();
    }

    /**
     * @return array{0:int,1:int}
     */
    private function seedTermAndType(int $yearId, string $suffix): array
    {
        $termId = (int) DB::table(SchemaHelper::qualified('academic', 'terms'))->insertGetId([
            'academic_year_id' => $yearId,
            'code' => 'T-'.$suffix.uniqid(),
            'name' => 'Term '.$suffix,
            'start_date' => '2026-09-01',
            'end_date' => '2026-12-31',
            'term_order' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $typeId = (int) DB::table(SchemaHelper::qualified('exams', 'exam_types'))->insertGetId([
            'code' => 'TX'.substr(uniqid(), -4),
            'name' => 'Type '.$suffix,
            'weight_percentage' => 40,
        ]);

        return [$termId, $typeId];
    }
}
