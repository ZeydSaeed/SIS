<?php

namespace Tests\Feature\Exams;

use App\Database\SchemaHelper;
use App\Domain\Exams\ValueObjects\GradeStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Exams\SeedsApplicationExamGradeGraph;
use Tests\TestCase;

class CorrectVoidFinalizeGradeApiTest extends TestCase
{
    use InteractsWithSecurity;
    use RefreshDatabase;
    use SeedsApplicationExamGradeGraph;

    #[Test]
    public function manager_can_correct_void_and_finalize(): void
    {
        $schoolId = $this->createSchool('SCH-GB', 'School GB');
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'B');
        $this->markSessionInProgressForGradeEntry($graph['session_id']);
        $this->actingAsGradesManagerForSchool($schoolId);

        $enter = $this->postJson('/api/v1/grades', [
            'exam_enrollment_id' => $graph['exam_enrollment_id'],
            'score' => 60,
            'is_absent' => false,
        ], ['X-Idempotency-Key' => 'cvf-enter'])->assertCreated();

        $gradeId = (int) $enter->json('data.id');
        $yearId = (int) $enter->json('data.academic_year_id');

        $corrected = $this->postJson("/api/v1/grades/{$gradeId}/correct", [
            'academic_year_id' => $yearId,
            'score' => 75,
            'is_absent' => false,
            'reason' => 'marking error',
        ], ['X-Idempotency-Key' => 'cvf-correct'])->assertOk();

        $newId = (int) $corrected->json('data.id');
        $this->assertNotSame($gradeId, $newId);

        $prior = DB::table(SchemaHelper::qualified('exams', 'student_grades'))->where('id', $gradeId)->first();
        $this->assertFalse((bool) $prior->is_current);
        $this->assertSame(GradeStatus::Voided->value, (int) $prior->status);

        $replacement = DB::table(SchemaHelper::qualified('exams', 'student_grades'))->where('id', $newId)->first();
        $this->assertTrue((bool) $replacement->is_current);
        $this->assertSame($gradeId, (int) $replacement->correction_of_grade_id);

        $this->postJson("/api/v1/grades/{$newId}/finalize", [
            'academic_year_id' => $yearId,
        ], ['X-Idempotency-Key' => 'cvf-fin'])->assertOk();

        $finalized = DB::table(SchemaHelper::qualified('exams', 'student_grades'))->where('id', $newId)->first();
        $this->assertSame(GradeStatus::Finalized->value, (int) $finalized->status);
        $this->assertNotNull($finalized->finalized_at);

        $this->postJson("/api/v1/grades/{$newId}/void", [
            'academic_year_id' => $yearId,
            'reason' => 'admin void after finalize',
        ], ['X-Idempotency-Key' => 'cvf-void'])->assertOk();

        $voided = DB::table(SchemaHelper::qualified('exams', 'student_grades'))->where('id', $newId)->first();
        $this->assertFalse((bool) $voided->is_current);
        $this->assertSame(GradeStatus::Voided->value, (int) $voided->status);
    }

    #[Test]
    public function delete_route_does_not_exist(): void
    {
        $schoolId = $this->createSchool('SCH-GB', 'School GB');
        $this->actingAsGradesManagerForSchool($schoolId);

        $this->deleteJson('/api/v1/grades/1')->assertStatus(405);
    }
}
