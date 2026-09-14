<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Domain\Exams\ValueObjects\ExamStatus;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;
use Tests\Support\Exams\SeedsApplicationExamGradeGraph;

final class PhaseExamHttpUpdateCancelHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;
    use SeedsApplicationExamGradeGraph;

    #[Test]
    public function manager_can_update_and_cancel_exam(): void
    {
        $schoolId = $this->createSchool('SCH-EX-UPD', 'Exam Update Cancel');
        $this->actingAsGradesManagerForSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'UPD');

        $this->patchJson('/api/v1/exams/'.$graph['exam_id'], [
            'name' => 'Updated Exam Name',
        ], ['X-Idempotency-Key' => 'exam-upd-1'])
            ->assertOk()
            ->assertJsonPath('data.id', $graph['exam_id']);

        $this->getJson('/api/v1/exams/'.$graph['exam_id'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Exam Name');

        $this->postJson('/api/v1/exams/'.$graph['exam_id'].'/cancel', [], [
            'X-Idempotency-Key' => 'exam-cancel-1',
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $graph['exam_id'])
            ->assertJsonPath('data.status', ExamStatus::Cancelled->value);
    }

    #[Test]
    public function manager_can_patch_session_and_enrollment(): void
    {
        $schoolId = $this->createSchool('SCH-EX-PATCH', 'Exam Patch Nested');
        $this->actingAsGradesManagerForSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'PCH');

        $this->patchJson('/api/v1/exam-sessions/'.$graph['session_id'], [
            'max_grade' => 90,
            'pass_grade' => 45,
        ], ['X-Idempotency-Key' => 'exam-sess-upd-1'])
            ->assertOk()
            ->assertJsonPath('data.id', $graph['session_id']);

        $this->patchJson('/api/v1/exam-enrollments/'.$graph['exam_enrollment_id'], [
            'seat_number' => 'Z9',
        ], ['X-Idempotency-Key' => 'exam-enr-upd-1'])
            ->assertOk()
            ->assertJsonPath('data.id', $graph['exam_enrollment_id']);

        $this->getJson('/api/v1/exam-enrollments/'.$graph['exam_enrollment_id'])
            ->assertOk()
            ->assertJsonPath('data.seat_number', 'Z9');
    }
}
