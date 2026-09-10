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

class EnterStudentGradeApiTest extends TestCase
{
    use InteractsWithSecurity;
    use RefreshDatabase;
    use SeedsApplicationExamGradeGraph;

    #[Test]
    public function grades_manager_can_enter_grade(): void
    {
        $schoolId = $this->createSchool('SCH-GA', 'School GA');
        $graph = $this->seedExamGradeGraph($schoolId);
        $this->actingAsGradesManagerForSchool($schoolId);

        $response = $this->postJson('/api/v1/grades', [
            'exam_enrollment_id' => $graph['exam_enrollment_id'],
            'score' => 88,
            'is_absent' => false,
        ], ['X-Idempotency-Key' => 'enter-1']);

        $response->assertCreated()
            ->assertJsonPath('data.academic_year_id', $graph['year_id']);

        $row = DB::table(SchemaHelper::qualified('exams', 'student_grades'))
            ->where('id', $response->json('data.id'))
            ->first();

        $this->assertNotNull($row);
        $this->assertEquals(100, (float) $row->max_score);
        $this->assertSame(GradeStatus::Entered->value, (int) $row->status);
        $this->assertTrue((bool) $row->is_current);
    }

    #[Test]
    public function enter_rejects_client_max_score(): void
    {
        $schoolId = $this->createSchool('SCH-GA', 'School GA');
        $graph = $this->seedExamGradeGraph($schoolId);
        $this->actingAsGradesManagerForSchool($schoolId);

        $this->postJson('/api/v1/grades', [
            'exam_enrollment_id' => $graph['exam_enrollment_id'],
            'score' => 88,
            'is_absent' => false,
            'max_score' => 50,
        ])->assertStatus(422);
    }

    #[Test]
    public function enter_is_idempotent_with_same_key(): void
    {
        $schoolId = $this->createSchool('SCH-GA', 'School GA');
        $graph = $this->seedExamGradeGraph($schoolId);
        $this->actingAsGradesManagerForSchool($schoolId);

        $first = $this->postJson('/api/v1/grades', [
            'exam_enrollment_id' => $graph['exam_enrollment_id'],
            'score' => 70,
            'is_absent' => false,
        ], ['X-Idempotency-Key' => 'idem-enter']);

        $first->assertCreated();

        $second = $this->postJson('/api/v1/grades', [
            'exam_enrollment_id' => $graph['exam_enrollment_id'],
            'score' => 70,
            'is_absent' => false,
        ], ['X-Idempotency-Key' => 'idem-enter']);

        $second->assertOk()
            ->assertJsonPath('data.id', $first->json('data.id'))
            ->assertJsonPath('meta.from_idempotency_cache', true);
    }

    #[Test]
    public function unauthenticated_enter_is_rejected(): void
    {
        $this->postJson('/api/v1/grades', [
            'exam_enrollment_id' => 1,
            'score' => 50,
            'is_absent' => false,
        ])->assertUnauthorized();
    }
}
