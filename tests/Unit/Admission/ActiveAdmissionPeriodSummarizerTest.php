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
                $this->period(1, 'دورة أ', ApplicationPeriodStatus::Active->value, 100),
                $this->period(2, 'دورة ب', ApplicationPeriodStatus::Inactive->value, 50),
                $this->period(3, 'دورة ج', ApplicationPeriodStatus::Active->value, null),
            ],
            [
                1 => ['total' => 12, 'submitted' => 8],
                2 => ['total' => 40, 'submitted' => 40],
                3 => ['total' => 3, 'submitted' => 1],
            ],
        );

        $this->assertCount(2, $summaries);
        $this->assertSame('دورة أ', $summaries[0]['name']);
        $this->assertSame(100, $summaries[0]['max_applications']);
        $this->assertSame(8, $summaries[0]['submitted_count']);
        $this->assertSame(88, $summaries[0]['remaining']);
        $this->assertSame('دورة ج', $summaries[1]['name']);
        $this->assertNull($summaries[1]['max_applications']);
        $this->assertSame(1, $summaries[1]['submitted_count']);
        $this->assertNull($summaries[1]['remaining']);
    }

    public function test_remaining_does_not_go_below_zero(): void
    {
        $summaries = (new ActiveAdmissionPeriodSummarizer)->summarize(
            [$this->period(4, 'ممتلئة', ApplicationPeriodStatus::Active->value, 5)],
            [4 => ['total' => 9, 'submitted' => 7]],
        );

        $this->assertSame(0, $summaries[0]['remaining']);
        $this->assertSame(7, $summaries[0]['submitted_count']);
    }

    /**
     * @return array{id:int, name:string, status:int, max_applications:?int}
     */
    private function period(int $id, string $name, int $status, ?int $max): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'status' => $status,
            'max_applications' => $max,
        ];
    }
}
