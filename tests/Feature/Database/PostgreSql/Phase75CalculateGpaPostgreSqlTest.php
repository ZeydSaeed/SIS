<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Application\Exams\Commands\EnterStudentGradeCommand;
use App\Application\Exams\Commands\EnterStudentGradeHandler;
use App\Application\Exams\Commands\FinalizeStudentGradeCommand;
use App\Application\Exams\Commands\FinalizeStudentGradeHandler;
use App\Application\Results\Commands\CalculateGpaCommand;
use App\Application\Results\Commands\CalculateGpaHandler;
use App\Application\Results\Commands\FinalizeAnnualResultCommand;
use App\Application\Results\Commands\FinalizeAnnualResultHandler;
use App\Application\Results\Commands\FinalizeTermResultCommand;
use App\Application\Results\Commands\FinalizeTermResultHandler;
use App\Database\SchemaHelper;
use App\Domain\Results\Exceptions\GpaOfficialAnnualMissingException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;
use Tests\Support\Exams\SeedsApplicationExamGradeGraph;

final class Phase75CalculateGpaPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;
    use SeedsApplicationExamGradeGraph;

    #[Test]
    public function calculate_gpa_requires_official_annual(): void
    {
        $schoolId = $this->createSchool('SCH-75-U02A', 'GPA A');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: '75A');

        $this->expectException(GpaOfficialAnnualMissingException::class);

        $this->app->make(CalculateGpaHandler::class)->handle(new CalculateGpaCommand(
            schoolId: $schoolId,
            enrollmentId: $graph['enrollment_id'],
            academicYearId: $graph['year_id'],
            idempotencyKey: '75a-gpa',
            createdBy: (int) $user->id,
        ));
    }

    #[Test]
    public function calculate_gpa_from_official_annual_percent_scale(): void
    {
        $schoolId = $this->createSchool('SCH-75-U02B', 'GPA B');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);

        $graph = $this->seedExamGradeGraph($schoolId, suffix: '75B');
        $typeId = (int) DB::table(SchemaHelper::qualified('exams', 'exams'))
            ->where('id', $graph['exam_id'])->value('exam_type_id');
        DB::table(SchemaHelper::qualified('exams', 'exam_types'))
            ->where('id', $typeId)->update(['weight_percentage' => 100]);
        $this->markSessionInProgressForGradeEntry($graph['session_id']);

        $enter = $this->app->make(EnterStudentGradeHandler::class)->handle(new EnterStudentGradeCommand(
            schoolId: $schoolId,
            examEnrollmentId: $graph['exam_enrollment_id'],
            score: '91',
            isAbsent: false,
            enteredBy: (int) $user->id,
            idempotencyKey: '75b-enter',
        ));
        $this->app->make(FinalizeStudentGradeHandler::class)->handle(new FinalizeStudentGradeCommand(
            schoolId: $schoolId,
            gradeId: (int) $enter->gradeId,
            academicYearId: $graph['year_id'],
            finalizedBy: (int) $user->id,
            idempotencyKey: '75b-gfin',
        ));
        $this->app->make(FinalizeTermResultHandler::class)->handle(new FinalizeTermResultCommand(
            schoolId: $schoolId,
            enrollmentId: $graph['enrollment_id'],
            academicYearId: $graph['year_id'],
            termId: $graph['term_id'],
            subjectId: $graph['subject_id'],
            idempotencyKey: '75b-tfin',
            createdBy: (int) $user->id,
        ));
        $this->app->make(FinalizeAnnualResultHandler::class)->handle(new FinalizeAnnualResultCommand(
            schoolId: $schoolId,
            enrollmentId: $graph['enrollment_id'],
            academicYearId: $graph['year_id'],
            idempotencyKey: '75b-afin',
            createdBy: (int) $user->id,
        ));

        $gpa = $this->app->make(CalculateGpaHandler::class)->handle(new CalculateGpaCommand(
            schoolId: $schoolId,
            enrollmentId: $graph['enrollment_id'],
            academicYearId: $graph['year_id'],
            idempotencyKey: '75b-gpa',
            createdBy: (int) $user->id,
        ));

        $this->assertTrue($gpa->success);
        $this->assertSame('91.00', $gpa->gpaValue);
        $this->assertSame('PERCENT_100', $gpa->scaleCode);
        $this->assertDatabaseHas(SchemaHelper::qualified('results', 'gpa_results'), [
            'id' => $gpa->gpaResultId,
            'is_current_operational' => true,
            'gpa_value' => '91.00',
            'scale_code' => 'PERCENT_100',
        ]);
    }
}
