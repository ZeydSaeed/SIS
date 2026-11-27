<?php

namespace Tests\Support\Results;

use App\Database\SchemaHelper;
use Illuminate\Support\Facades\DB;

/**
 * Test helper (EXAMIS U-01 / U-02): the result-sheet scene where the student with a finalized grade gets an official
 * term, annual and year GPA result through the results API; the two other students move to a second section, so the
 * scene section is complete and can be published. Needs {@see SeedsResultSheetScene} and InteractsWithSecurity.
 */
trait SeedsOfficialYearScene
{
    /** @return array<string, mixed> */
    protected function officialScene(): array
    {
        $s = $this->scene();
        $this->actingAsResultsManagerForSchool($s['school']);
        $term = ['enrollment_id' => $s['final'], 'academic_year_id' => $s['year_id'], 'term_id' => $s['term_id'], 'subject_id' => $s['subject_id']];
        $year = ['enrollment_id' => $s['final'], 'academic_year_id' => $s['year_id']];
        foreach ([['term/calculate', $term], ['term/finalize', $term], ['annual/calculate', $year], ['annual/finalize', $year], ['gpa/calculate', $year], ['gpa/finalize', $year]] as $i => [$path, $body]) {
            $this->postJson('/api/v1/results/'.$path, $body, ['X-Idempotency-Key' => 'u01-'.$i])->assertSuccessful();
        }
        $this->flushHeaders();
        $s['elsewhere'] = (int) $this->createSectionForClass($s['class'])->id;
        DB::table(SchemaHelper::qualified('enrollment', 'enrollments'))->whereIn('id', [$s['entered'], $s['ungraded']])->update(['section_id' => $s['elsewhere']]);

        return $s;
    }

    protected function studentOf(int $enrollmentId): int
    {
        return (int) DB::table(SchemaHelper::qualified('enrollment', 'enrollments'))->where('id', $enrollmentId)->value('student_id');
    }
}
