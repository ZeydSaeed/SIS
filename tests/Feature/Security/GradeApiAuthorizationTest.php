<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Exams\SeedsApplicationExamGradeGraph;
use Tests\TestCase;

class GradeApiAuthorizationTest extends TestCase
{
    use InteractsWithSecurity;
    use RefreshDatabase;
    use SeedsApplicationExamGradeGraph;

    #[Test]
    public function viewer_cannot_enter_grade(): void
    {
        $schoolId = $this->createSchool('SCH-GV', 'School GV');
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'V');
        $this->actingAsGradesViewer(schoolId: $schoolId);

        $this->postJson('/api/v1/grades', [
            'exam_enrollment_id' => $graph['exam_enrollment_id'],
            'score' => 50,
            'is_absent' => false,
        ])->assertForbidden();
    }

    #[Test]
    public function teacher_can_enter_but_not_finalize(): void
    {
        $schoolId = $this->createSchool('SCH-GT', 'School GT');
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'T');
        $this->actingAsGradesTeacher(schoolId: $schoolId);

        $enter = $this->postJson('/api/v1/grades', [
            'exam_enrollment_id' => $graph['exam_enrollment_id'],
            'score' => 55,
            'is_absent' => false,
        ])->assertCreated();

        $this->postJson('/api/v1/grades/'.$enter->json('data.id').'/finalize', [
            'academic_year_id' => $enter->json('data.academic_year_id'),
        ])->assertForbidden();
    }

    #[Test]
    public function cross_school_enter_is_rejected(): void
    {
        $schoolA = $this->createSchool('SCH-GA1', 'School GA1');
        $schoolB = $this->createSchool('SCH-GB1', 'School GB1');
        $graphB = $this->seedExamGradeGraph($schoolB, suffix: 'XB');

        $this->actingAsGradesManagerForSchool($schoolA);

        $this->postJson('/api/v1/grades', [
            'exam_enrollment_id' => $graphB['exam_enrollment_id'],
            'score' => 40,
            'is_absent' => false,
        ])->assertNotFound();
    }
}
