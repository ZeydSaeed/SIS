<?php

namespace Tests\Unit\Admission;

use App\Domain\Admission\Services\ActiveAdmissionPeriodSummarizer;
use App\Domain\Admission\ValueObjects\ApplicationPeriodStatus;
use PHPUnit\Framework\TestCase;

class ActiveAdmissionPeriodSummarizerTest extends TestCase
{
    public function test_summarizes_active_periods_only(): void
    {
        $summaries = (new ActiveAdmissionPeriodSummarizer)->summarize(
            [
                $this->period(1, 'دورة أ', ApplicationPeriodStatus::Active->value, 100, '2026-09-01'),
                $this->period(2, 'دورة ب', ApplicationPeriodStatus::Inactive->value, 50, '2026-08-01'),
                $this->period(3, 'دورة ج', ApplicationPeriodStatus::Active->value, null, '2026-10-01'),
            ],
            [
                1 => ['total' => 12, 'submitted' => 8],
                2 => ['total' => 40, 'submitted' => 40],
                3 => ['total' => 3, 'submitted' => 1],
            ],
        );

        $this->assertCount(2, $summaries);
        $this->assertSame('دورة أ', $summaries[0]['name']);
        $this->assertSame(12, $summaries[0]['total_count']);
        $this->assertSame(100, $summaries[0]['max_applications']);
        $this->assertSame(8, $summaries[0]['submitted_count']);
        $this->assertSame(88, $summaries[0]['remaining']);
        $this->assertSame('دورة ج', $summaries[1]['name']);
        $this->assertNull($summaries[1]['max_applications']);
        $this->assertSame(1, $summaries[1]['submitted_count']);
        $this->assertNull($summaries[1]['remaining']);
    }

    public function test_sorts_active_periods_oldest_to_newest(): void
    {
        $summaries = (new ActiveAdmissionPeriodSummarizer)->summarize(
            [
                $this->period(9, 'جديدة', ApplicationPeriodStatus::Active->value, 10, '2026-10-01'),
                $this->period(4, 'قديمة', ApplicationPeriodStatus::Active->value, 10, '2026-01-01'),
            ],
            [],
        );

        $this->assertSame([4, 9], array_column($summaries, 'id'));
    }

    public function test_remaining_does_not_go_below_zero(): void
    {
        $summaries = (new ActiveAdmissionPeriodSummarizer)->summarize(
            [$this->period(4, 'ممتلئة', ApplicationPeriodStatus::Active->value, 5, '2026-01-01')],
            [4 => ['total' => 9, 'submitted' => 7]],
        );

        $this->assertSame(0, $summaries[0]['remaining']);
        $this->assertSame(9, $summaries[0]['total_count']);
        $this->assertSame(7, $summaries[0]['submitted_count']);
    }

    public function test_resolve_selected_id_prefers_requested_active_period(): void
    {
        $summarizer = new ActiveAdmissionPeriodSummarizer;
        $summaries = $summarizer->summarize(
            [
                $this->period(4, 'قديمة', ApplicationPeriodStatus::Active->value, 10, '2026-01-01'),
                $this->period(9, 'جديدة', ApplicationPeriodStatus::Active->value, 10, '2026-10-01'),
            ],
            [],
        );

        $this->assertNull($summarizer->resolveSelectedId($summaries, null));
        $this->assertNull($summarizer->resolveSelectedId($summaries, 0));
        $this->assertSame(9, $summarizer->resolveSelectedId($summaries, 9));
        $this->assertNull($summarizer->resolveSelectedId($summaries, 99));
        $this->assertNull($summarizer->resolveSelectedId([], 9));
    }

    public function test_applications_and_documents_are_scoped_to_period(): void
    {
        $summarizer = new ActiveAdmissionPeriodSummarizer;
        $apps = [
            ['id' => 1, 'application_period_id' => 4],
            ['id' => 2, 'application_period_id' => 9],
            ['id' => 3, 'application_period_id' => 4],
        ];
        $docs = [
            ['id' => 10, 'application_id' => 1],
            ['id' => 11, 'application_id' => 2],
            ['id' => 12, 'application_id' => 3],
        ];

        $scoped = $summarizer->applicationsInPeriod($apps, 4);

        $this->assertSame([1, 3], array_column($scoped, 'id'));
        $this->assertSame(
            [10, 12],
            array_column($summarizer->documentsForApplications($docs, $scoped), 'id'),
        );
        $this->assertSame([], $summarizer->applicationsInPeriod($apps, null));
    }

    public function test_applications_in_active_periods_exclude_inactive_and_archived(): void
    {
        $summarizer = new ActiveAdmissionPeriodSummarizer;
        $apps = [
            ['id' => 1, 'application_period_id' => 4],
            ['id' => 2, 'application_period_id' => 8],
            ['id' => 3, 'application_period_id' => 9],
            ['id' => 4, 'application_period_id' => 2],
        ];
        $active = [
            ['id' => 4],
            ['id' => 9],
        ];

        $scoped = $summarizer->applicationsInActivePeriods($apps, $active);

        $this->assertSame([1, 3], array_column($scoped, 'id'));
        $this->assertSame([], $summarizer->applicationsInActivePeriods($apps, []));
    }

    public function test_capacity_percent_uses_total_against_max(): void
    {
        $summarizer = new ActiveAdmissionPeriodSummarizer;

        $this->assertSame(5, $summarizer->capacityPercent(220, 11));
        $this->assertSame(1, $summarizer->capacityPercent(220, 1));
        $this->assertSame(100, $summarizer->capacityPercent(10, 40));
        $this->assertSame(0, $summarizer->capacityPercent(null, 11));
        $this->assertSame(0, $summarizer->capacityPercent(220, 0));
    }

    /**
     * @return array{id:int, name:string, status:int, max_applications:?int, start_date:string}
     */
    private function period(int $id, string $name, int $status, ?int $max, string $startDate): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'status' => $status,
            'max_applications' => $max,
            'start_date' => $startDate,
        ];
    }
}
