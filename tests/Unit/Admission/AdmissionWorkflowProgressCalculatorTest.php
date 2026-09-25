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
        $this->assertSame(0, $result['stage_percents'][ApplicationStatus::Submitted->value]);
        $this->assertSame(0, $result['stage_percents'][ApplicationStatus::Accepted->value]);
    }

    public function test_occupancy_and_overall_from_current_statuses(): void
    {
        $statuses = [
            ApplicationStatus::Submitted->value,
            ApplicationStatus::Submitted->value,
            ApplicationStatus::UnderReview->value,
            ApplicationStatus::Accepted->value,
        ];

        $result = $this->calculator()->calculate($this->pipeline(), $statuses);

        $this->assertSame(50, $result['stage_percents'][ApplicationStatus::Submitted->value]);
        $this->assertSame(25, $result['stage_percents'][ApplicationStatus::UnderReview->value]);
        $this->assertSame(25, $result['stage_percents'][ApplicationStatus::Accepted->value]);
        $this->assertSame(0, $result['stage_percents'][ApplicationStatus::Interview->value]);
        $this->assertSame(45, $result['overall_percent']);
    }

    public function test_withdrawn_and_rejected_are_not_pipeline_stages(): void
    {
        $pipeline = $this->pipeline();

        $this->assertNotContains(ApplicationStatus::Withdrawn->value, $pipeline);
        $this->assertNotContains(ApplicationStatus::Rejected->value, $pipeline);
        $this->assertNotContains(ApplicationStatus::Draft->value, $pipeline);
        $this->assertNotContains(ApplicationStatus::Converted->value, $pipeline);
    }

    public function test_calculate_from_counts_matches_expanded_statuses(): void
    {
        $counts = [
            ApplicationStatus::Submitted->value => 2,
            ApplicationStatus::UnderReview->value => 1,
            ApplicationStatus::Accepted->value => 1,
        ];

        $fromCounts = $this->calculator()->calculateFromCounts($this->pipeline(), $counts);
        $fromList = $this->calculator()->calculate($this->pipeline(), [
            ApplicationStatus::Submitted->value,
            ApplicationStatus::Submitted->value,
            ApplicationStatus::UnderReview->value,
            ApplicationStatus::Accepted->value,
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
