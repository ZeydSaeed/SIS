<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Application\Exams\Commands\EnterStudentGradeCommand;
use App\Application\Exams\Commands\EnterStudentGradeHandler;
use App\Application\Results\Commands\CalculateAnnualResultCommand;
use App\Application\Results\Commands\CalculateAnnualResultHandler;
use App\Application\Results\Commands\CalculateTermResultCommand;
use App\Application\Results\Commands\CalculateTermResultHandler;
use App\Database\SchemaHelper;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;
use Tests\Support\Exams\SeedsApplicationExamGradeGraph;

final class Phase74CalculateAnnualResultPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;
    use SeedsApplicationExamGradeGraph;

    #[Test]
    public function calculate_annual_result_from_operational_term_rows(): void
    {
        $schoolId = $this->createSchool('SCH-74-U06', 'Annual U06');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);

        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'U06');
        $typeId = (int) DB::table(SchemaHelper::qualified('exams', 'exams'))
            ->where('id', $graph['exam_id'])
            ->value('exam_type_id');
        DB::table(SchemaHelper::qualified('exams', 'exam_types'))
            ->where('id', $typeId)
            ->update(['weight_percentage' => 100]);
        $this->markSessionInProgressForGradeEntry($graph['session_id']);

        $this->app->make(EnterStudentGradeHandler::class)->handle(new EnterStudentGradeCommand(
            schoolId: $schoolId,
            examEnrollmentId: $graph['exam_enrollment_id'],
            score: '80',
            isAbsent: false,
            enteredBy: (int) $user->id,
            idempotencyKey: 'u06-enter',
        ));

        $this->app->make(CalculateTermResultHandler::class)->handle(new CalculateTermResultCommand(
            schoolId: $schoolId,
            enrollmentId: $graph['enrollment_id'],
            academicYearId: $graph['year_id'],
            termId: $graph['term_id'],
            subjectId: $graph['subject_id'],
            idempotencyKey: 'u06-term',
            createdBy: (int) $user->id,
        ));

        $annual = $this->app->make(CalculateAnnualResultHandler::class)->handle(new CalculateAnnualResultCommand(
            schoolId: $schoolId,
            enrollmentId: $graph['enrollment_id'],
            academicYearId: $graph['year_id'],
            idempotencyKey: 'u06-annual',
            createdBy: (int) $user->id,
        ));

        $this->assertTrue($annual->success);
        $this->assertSame('80.00', $annual->averageWeightedTotal);
        $this->assertDatabaseHas(SchemaHelper::qualified('results', 'annual_results'), [
            'id' => $annual->annualResultId,
            'is_current_operational' => true,
            'subjects_counted' => 1,
            'average_weighted_total' => '80.00',
        ]);
    }
}
