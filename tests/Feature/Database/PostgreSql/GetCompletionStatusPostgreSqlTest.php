<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Application\Graduation\Contracts\GraduationReadRepositoryInterface;
use App\Application\Graduation\Queries\GetCompletionStatusHandler;
use App\Application\Graduation\Queries\GetCompletionStatusQuery;
use App\Domain\Graduation\Exceptions\CompletionOutcomeNotFoundException;
use App\Domain\Graduation\Exceptions\GraduationAuthorityDeniedException;
use App\Security\Context\SchoolContext;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;
use Tests\Support\Database\PostgreSqlRlsActor;
use Tests\Support\Database\SeedsGraduationConcurrencyGraph;

final class GetCompletionStatusPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use SeedsGraduationConcurrencyGraph;

    private function bindSchool(int $schoolId, bool $isLocal = true): void
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, ?)", [(string) $schoolId, $isLocal ? 'true' : 'false']);
        $this->app->make(SchoolContext::class)->set($schoolId);
    }

    #[Test]
    public function returns_completion_status_for_school_scoped_outcome(): void
    {
        $g = $this->seedGraduationConcurrencyGraph();
        $this->bindSchool($g['school_a']);

        $outcomeId = (int) DB::table('graduation.completion_outcomes')->insertGetId([
            'school_id' => $g['school_a'],
            'enrollment_id' => $g['enrollment_a'],
            'student_id' => $g['student_a'],
            'academic_year_id' => $g['year_id'],
            'created_at' => now(),
        ]);

        $versionId = (int) DB::table('graduation.completion_outcome_versions')->insertGetId([
            'school_id' => $g['school_a'],
            'completion_outcome_id' => $outcomeId,
            'version_no' => 1,
            'lifecycle_status' => 1,
            'evaluation_status' => 2,
            'eligibility_status' => 2,
            'eligibility_policy_version_id' => $g['policy_version_id'],
            'calculation_version' => 'calc-read-1',
            'evaluated_at' => now(),
            'is_current_official' => false,
            'created_at' => now(),
        ]);

        $dto = $this->app->make(GetCompletionStatusHandler::class)->handle(
            new GetCompletionStatusQuery($g['school_a'], $g['enrollment_a']),
        );

        $this->assertSame($outcomeId, $dto->completionOutcomeId);
        $this->assertSame($versionId, $dto->versionId);
        $this->assertSame(2, $dto->eligibilityStatus);
        $this->assertSame('calc-read-1', $dto->calculationVersion);
        $this->assertSame($g['student_a'], $dto->studentId);
    }

    #[Test]
    public function school_b_context_cannot_read_school_a_via_handler_authority(): void
    {
        $g = $this->seedGraduationConcurrencyGraph();
        $this->bindSchool($g['school_a']);

        DB::table('graduation.completion_outcomes')->insert([
            'school_id' => $g['school_a'],
            'enrollment_id' => $g['enrollment_a'],
            'student_id' => $g['student_a'],
            'academic_year_id' => $g['year_id'],
            'created_at' => now(),
        ]);

        $this->bindSchool($g['school_b']);

        $this->expectException(GraduationAuthorityDeniedException::class);
        $this->app->make(GetCompletionStatusHandler::class)->handle(
            new GetCompletionStatusQuery($g['school_a'], $g['enrollment_a']),
        );
    }

    #[Test]
    public function rls_actor_under_school_b_cannot_see_school_a_completion_outcomes(): void
    {
        $g = $this->seedGraduationConcurrencyGraph();
        $this->bindSchool($g['school_a']);

        $outcomeA = (int) DB::table('graduation.completion_outcomes')->insertGetId([
            'school_id' => $g['school_a'],
            'enrollment_id' => $g['enrollment_a'],
            'student_id' => $g['student_a'],
            'academic_year_id' => $g['year_id'],
            'created_at' => now(),
        ]);

        $this->bindSchool($g['school_b']);
        $outcomeB = (int) DB::table('graduation.completion_outcomes')->insertGetId([
            'school_id' => $g['school_b'],
            'enrollment_id' => $g['enrollment_b'],
            'student_id' => $g['student_b'],
            'academic_year_id' => $g['year_id'],
            'created_at' => now(),
        ]);

        PostgreSqlRlsActor::become();

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $g['school_b']]);
        $ids = collect(DB::select('SELECT id FROM graduation.completion_outcomes'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertSame([$outcomeB], $ids);
        $this->assertNotContains($outcomeA, $ids);

        $repo = $this->app->make(GraduationReadRepositoryInterface::class);
        $this->assertNull($repo->findCompletionStatus($g['school_a'], $g['enrollment_a']));
        $visible = $repo->findCompletionStatus($g['school_b'], $g['enrollment_b']);
        $this->assertNotNull($visible);
        $this->assertSame($outcomeB, $visible->completionOutcomeId);

        PostgreSqlRlsActor::reset();
    }

    #[Test]
    public function missing_outcome_is_not_found(): void
    {
        $g = $this->seedGraduationConcurrencyGraph();
        $this->bindSchool($g['school_a']);

        $this->expectException(CompletionOutcomeNotFoundException::class);
        $this->app->make(GetCompletionStatusHandler::class)->handle(
            new GetCompletionStatusQuery($g['school_a'], $g['enrollment_a']),
        );
    }
}
