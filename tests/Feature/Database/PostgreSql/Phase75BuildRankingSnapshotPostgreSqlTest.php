<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Application\Results\Commands\BuildRankingSnapshotCommand;
use App\Application\Results\Commands\BuildRankingSnapshotHandler;
use App\Database\SchemaHelper;
use App\Domain\Results\Exceptions\RankingNoParticipantsException;
use App\Domain\Results\ValueObjects\GpaScope;
use App\Domain\Results\ValueObjects\ResultsLifecycleStatus;
use App\Infrastructure\Persistence\Eloquent\EnrollmentRecord;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class Phase75BuildRankingSnapshotPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function build_ranking_snapshot_ranks_official_year_gpa_in_class(): void
    {
        $schoolId = $this->createSchool('SCH-75-U06', 'Rank U06');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);

        $yearId = $this->createAcademicYear();
        $class = $this->createClassForSchool($schoolId, $yearId);
        $section = $this->createSectionForClass((int) $class->id);

        $high = $this->insertEnrollmentWithOfficialGpa($schoolId, $yearId, (int) $class->id, (int) $section->id, '95.00', 'A');
        $midA = $this->insertEnrollmentWithOfficialGpa($schoolId, $yearId, (int) $class->id, (int) $section->id, '88.00', 'B');
        $midB = $this->insertEnrollmentWithOfficialGpa($schoolId, $yearId, (int) $class->id, (int) $section->id, '88.00', 'C');
        $low = $this->insertEnrollmentWithOfficialGpa($schoolId, $yearId, (int) $class->id, (int) $section->id, '70.00', 'D');

        $result = $this->app->make(BuildRankingSnapshotHandler::class)->handle(new BuildRankingSnapshotCommand(
            schoolId: $schoolId,
            academicYearId: $yearId,
            classId: (int) $class->id,
            idempotencyKey: '75-u06-rank',
            createdBy: (int) $user->id,
        ));

        $this->assertTrue($result->success);
        $this->assertSame(4, $result->participantCount);
        $this->assertDatabaseHas(SchemaHelper::qualified('results', 'ranking_snapshots'), [
            'id' => $result->rankingSnapshotId,
            'class_id' => $class->id,
            'participant_count' => 4,
            'is_current' => true,
        ]);

        $entries = DB::table(SchemaHelper::qualified('results', 'ranking_snapshot_entries'))
            ->where('ranking_snapshot_id', $result->rankingSnapshotId)
            ->orderBy('rank_position')
            ->orderBy('enrollment_id')
            ->get(['enrollment_id', 'rank_position', 'metric_value']);

        $this->assertCount(4, $entries);
        $this->assertSame($high['enrollment_id'], (int) $entries[0]->enrollment_id);
        $this->assertSame(1, (int) $entries[0]->rank_position);
        $this->assertSame(2, (int) $entries[1]->rank_position);
        $this->assertSame(2, (int) $entries[2]->rank_position);
        $this->assertSame($low['enrollment_id'], (int) $entries[3]->enrollment_id);
        $this->assertSame(4, (int) $entries[3]->rank_position);
        unset($midA, $midB);
    }

    #[Test]
    public function build_ranking_snapshot_fails_without_participants(): void
    {
        $schoolId = $this->createSchool('SCH-75-U06E', 'Rank empty');
        $this->actingAsGradesManagerForSchool($schoolId);
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
        $yearId = $this->createAcademicYear();
        $class = $this->createClassForSchool($schoolId, $yearId);

        $this->expectException(RankingNoParticipantsException::class);
        $this->app->make(BuildRankingSnapshotHandler::class)->handle(new BuildRankingSnapshotCommand(
            schoolId: $schoolId,
            academicYearId: $yearId,
            classId: (int) $class->id,
            idempotencyKey: '75-u06-empty',
        ));
    }

    /**
     * @return array{enrollment_id:int,gpa_result_id:int}
     */
    private function insertEnrollmentWithOfficialGpa(
        int $schoolId,
        int $yearId,
        int $classId,
        int $sectionId,
        string $gpa,
        string $tag,
    ): array {
        $student = $this->createStudentForSchool($schoolId, [
            'student_code' => 'STU-U06-'.$tag.'-'.uniqid(),
            'first_name' => 'Rank'.$tag,
        ]);

        $enrollment = new EnrollmentRecord;
        $enrollment->forceFill([
            'student_id' => $student->id,
            'academic_year_id' => $yearId,
            'school_id' => $schoolId,
            'class_id' => $classId,
            'section_id' => $sectionId,
            'enrollment_number' => 'ENR-U06-'.$tag.'-'.uniqid(),
            'status' => 1,
            'effective_from' => '2026-09-01',
        ]);
        $enrollment->save();

        $gpaId = (int) DB::table(SchemaHelper::qualified('results', 'gpa_results'))->insertGetId([
            'school_id' => $schoolId,
            'enrollment_id' => $enrollment->id,
            'student_id' => $student->id,
            'academic_year_id' => $yearId,
            'gpa_scope' => GpaScope::AcademicYear->value,
            'result_version' => 1,
            'lifecycle_status' => ResultsLifecycleStatus::Finalized->value,
            'is_official' => true,
            'is_current_operational' => false,
            'is_current_official' => true,
            'gpa_value' => $gpa,
            'scale_code' => 'PERCENT_100',
            'source_annual_result_id' => null,
            'incomplete' => false,
            'source_fingerprint' => hash('sha256', 'u06-'.$tag.'-'.$gpa),
            'calculation_version' => 1,
            'policy_pin' => json_encode(['test' => true]),
            'calculated_at' => now(),
            'finalized_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'enrollment_id' => (int) $enrollment->id,
            'gpa_result_id' => $gpaId,
        ];
    }
}
