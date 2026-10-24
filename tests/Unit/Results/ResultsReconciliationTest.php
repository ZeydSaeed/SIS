<?php

namespace Tests\Unit\Results;

use App\Domain\Results\Services\ResultsReconciliation;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ResultsReconciliationTest extends TestCase
{
    /** @return array<string, mixed> */
    private function termRow(int $id, int $current, int $final, ?string $operational, ?string $official, bool $incomplete = false): array
    {
        return [
            'enrollment_id' => $id, 'seats' => 1, 'current_grades' => $current, 'finalized_grades' => $final,
            'operational' => $operational === null ? null : ['version' => 1, 'weighted_total' => $operational, 'incomplete' => false],
            'official' => $official === null ? null : ['version' => 2, 'weighted_total' => $official, 'incomplete' => $incomplete],
        ];
    }

    /** @param  array{issues: list<array{code: string, count: int, enrollment_ids: list<int>}>}  $report */
    private function codes(array $report): array
    {
        return array_column($report['issues'], 'count', 'code');
    }

    #[Test]
    public function an_empty_scope_is_never_ready(): void
    {
        $this->assertSame(['students' => 0, 'issues' => [], 'ready' => ['term' => false]], ResultsReconciliation::forTerm([]));
    }

    #[Test]
    public function term_scope_is_ready_only_when_every_student_has_a_complete_official_result(): void
    {
        $ok = ResultsReconciliation::forTerm([$this->termRow(1, 1, 1, '80.00', '80.00'), $this->termRow(2, 1, 1, '60.00', '60.00')]);
        $this->assertTrue($ok['ready']['term']);
        $this->assertSame([], $ok['issues']);

        $mixed = ResultsReconciliation::forTerm([
            $this->termRow(1, 1, 1, '80.00', '80.00'),
            $this->termRow(2, 1, 0, '60.00', null),
            $this->termRow(3, 0, 0, null, null),
            $this->termRow(4, 1, 1, '70.00', '70.00', incomplete: true),
        ]);
        $this->assertFalse($mixed['ready']['term']);
        $this->assertSame(['grades_not_finalized' => 1, 'not_finalized' => 1, 'not_graded' => 1, 'no_result' => 1, 'official_incomplete' => 1], $this->codes($mixed));
    }

    #[Test]
    public function a_calculated_value_that_differs_from_the_official_one_is_reported_but_does_not_block(): void
    {
        $report = ResultsReconciliation::forTerm([$this->termRow(7, 1, 1, '85.00', '80.00')]);

        $this->assertSame(['operational_differs' => 1], $this->codes($report));
        $this->assertSame([7], $report['issues'][0]['enrollment_ids']);
        $this->assertTrue($report['ready']['term']);
    }

    /** @return array<string, mixed> */
    private function yearRow(int $id, ?string $annual, ?string $gpa, bool $transcript, bool $upToDate = true, int $officialTerms = 2): array
    {
        $cell = static fn (?string $v): ?array => $v === null ? null : ['version' => 1, 'value' => $v, 'incomplete' => false];

        return [
            'enrollment_id' => $id, 'term_results' => ['operational' => 2, 'official' => $officialTerms],
            'annual' => ['operational' => $cell($annual), 'official' => $cell($annual)],
            'gpa' => ['operational' => $cell($gpa), 'official' => $cell($gpa)],
            'rank' => null,
            'transcript' => $transcript ? ['number' => 'T-1', 'version' => 1, 'up_to_date' => $upToDate] : null,
        ];
    }

    #[Test]
    public function year_scope_reports_each_publication_kind_separately(): void
    {
        $report = ResultsReconciliation::forYear([
            $this->yearRow(1, '80.00', '80.00', true),
            $this->yearRow(2, '70.00', null, true, upToDate: false),
        ], hasRanking: true);

        $this->assertSame(['annual' => true, 'gpa' => false, 'transcript' => false], $report['ready']);
        $this->assertSame(['gpa_missing' => 1, 'transcript_outdated' => 1], $this->codes($report));
    }

    #[Test]
    public function year_scope_flags_missing_term_results_and_a_missing_ranking(): void
    {
        $report = ResultsReconciliation::forYear([$this->yearRow(1, '80.00', '80.00', true, officialTerms: 0)], hasRanking: false);

        $this->assertSame(['no_official_term_results' => 1, 'ranking_missing' => 1], $this->codes($report));
        $this->assertSame([], array_values(array_filter($report['issues'], static fn (array $i): bool => $i['code'] === 'ranking_missing' && $i['enrollment_ids'] !== [])));
    }
}
