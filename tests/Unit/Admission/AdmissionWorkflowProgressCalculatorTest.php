<?php

namespace Tests\Unit\Admission;

use App\Domain\Admission\Services\AdmissionWorkflowProgressCalculator;
use App\Domain\Admission\ValueObjects\ApplicationStatus;
use PHPUnit\Framework\TestCase;

class AdmissionWorkflowProgressCalculatorTest extends TestCase
{
    public function test_empty_applications_are_zero(): void
    {
        $result = $this->calculator()->calculate($this->pipeline(), []);

        $this->assertSame(0, $result['overall_percent']);
        $this->assertSame(0, $result['stage_percents'][ApplicationStatus::Draft->value]);
        $this->assertSame(0, $result['stage_percents'][ApplicationStatus::Converted->value]);
    }

    public function test_occupancy_and_overall_from_current_statuses(): void
    {
        $statuses = [
            ApplicationStatus::Draft->value,
            ApplicationStatus::Draft->value,
            ApplicationStatus::Submitted->value,
            ApplicationStatus::Converted->value,
        ];

        $result = $this->calculator()->calculate($this->pipeline(), $statuses);

        $this->assertSame(50, $result['stage_percents'][ApplicationStatus::Draft->value]);
        $this->assertSame(25, $result['stage_percents'][ApplicationStatus::Submitted->value]);
        $this->assertSame(25, $result['stage_percents'][ApplicationStatus::Converted->value]);
        $this->assertSame(0, $result['stage_percents'][ApplicationStatus::Accepted->value]);
        $this->assertSame(39, $result['overall_percent']);
    }

    public function test_rejected_counts_in_overall_denominator_not_stage_occupancy(): void
    {
        $statuses = [
            ApplicationStatus::Draft->value,
            ApplicationStatus::Rejected->value,
        ];

        $result = $this->calculator()->calculate($this->pipeline(), $statuses);

        $this->assertSame(50, $result['stage_percents'][ApplicationStatus::Draft->value]);
        $this->assertSame(7, $result['overall_percent']);
    }

    public function test_calculate_from_counts_matches_expanded_statuses(): void
    {
        $counts = [
            ApplicationStatus::Draft->value => 2,
            ApplicationStatus::Submitted->value => 1,
            ApplicationStatus::Converted->value => 1,
        ];

        $fromCounts = $this->calculator()->calculateFromCounts($this->pipeline(), $counts);
        $fromList = $this->calculator()->calculate($this->pipeline(), [
            ApplicationStatus::Draft->value,
            ApplicationStatus::Draft->value,
            ApplicationStatus::Submitted->value,
            ApplicationStatus::Converted->value,
        ]);

        $this->assertSame($fromList, $fromCounts);
    }

    /**
     * @return list<int>
     */
    private function pipeline(): array
    {
        $pipeline = [];
        foreach (ApplicationStatus::pipelineSteps() as $status) {
            $pipeline[] = $status->value;
        }

        return $pipeline;
    }

    private function calculator(): AdmissionWorkflowProgressCalculator
    {
        return new AdmissionWorkflowProgressCalculator;
    }
}
