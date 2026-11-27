<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;
use Tests\Support\Results\PublishesOfficialResults;

/** EXAMIS operations T-3: the read-only drift report and its exit code / log line (the alert hook). */
final class PublicationDriftReportPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;
    use PublishesOfficialResults;

    /** @return array{school: int, year: int, enrollment: int} */
    private function published(string $code, int $officialVersion, int $publishedVersion): array
    {
        $school = $this->createSchool($code, 'Drift '.$code);
        $year = $this->createAcademicYear('AY-'.$code);
        $enrollment = $this->createActiveEnrollmentForSchool($school, $year);
        DB::table(SchemaHelper::qualified('results', 'annual_results'))->insert([
            'school_id' => $school, 'enrollment_id' => $enrollment->id, 'student_id' => $enrollment->student_id, 'academic_year_id' => $year,
            'result_version' => $officialVersion, 'lifecycle_status' => 2, 'is_official' => true, 'is_current_official' => true,
            'average_weighted_total' => '80.00', 'incomplete' => false, 'source_fingerprint' => hash('sha256', $code), 'policy_pin' => '{}',
            'calculated_at' => now(), 'finalized_at' => now(),
        ]);
        $this->publishOfficialResult($school, $year, (int) $enrollment->id, 'annual', $publishedVersion);

        return ['school' => $school, 'year' => $year, 'enrollment' => (int) $enrollment->id];
    }

    #[Test]
    public function no_drift_exits_zero_and_logs_nothing(): void
    {
        $a = $this->published('DRF-A', 1, 1);
        Log::spy();

        $this->artisan('sis:report-publication-drift', ['--school' => $a['school']])->expectsOutputToContain('0 with drift')->assertExitCode(0);
        Log::shouldNotHaveReceived('error');
    }

    #[Test]
    public function drift_is_reported_per_school_year_and_kind_exits_one_and_logs_one_line_per_publication(): void
    {
        $clean = $this->published('DRF-C', 1, 1);
        $drifted = $this->published('DRF-D', 2, 1);
        Log::spy();

        $this->artisan('sis:report-publication-drift', ['--school' => $drifted['school'], '--json' => true])
            ->expectsOutputToContain('"drifted":[{')->assertExitCode(1);
        Log::shouldHaveReceived('error')->once()->withArgs(fn (string $message, array $context): bool => $message === 'results.publication.drift'
            && $context['school_id'] === $drifted['school'] && $context['academic_year_id'] === $drifted['year'] && $context['kind'] === 'annual' && $context['drifted'] === 1);

        $this->artisan('sis:report-publication-drift', ['--school' => $clean['school']])->assertExitCode(0);
    }

    #[Test]
    public function the_report_only_reads(): void
    {
        $d = $this->published('DRF-R', 2, 1);
        $before = [DB::table(SchemaHelper::qualified('results', 'result_publications'))->count(), DB::table(SchemaHelper::qualified('results', 'annual_results'))->count()];

        $this->artisan('sis:report-publication-drift', ['--school' => $d['school']])->assertExitCode(1);

        $this->assertSame($before, [DB::table(SchemaHelper::qualified('results', 'result_publications'))->count(), DB::table(SchemaHelper::qualified('results', 'annual_results'))->count()]);
        $this->assertSame('published', DB::table(SchemaHelper::qualified('results', 'result_publications'))->where('school_id', $d['school'])->value('status'));
    }
}
