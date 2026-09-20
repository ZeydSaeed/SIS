<?php

namespace App\Application\Admission\DTOs;

use App\Domain\Admission\ValueObjects\ApplicationStatus;

final readonly class AdmissionWorkspaceDTO
{
    /**
     * @param  list<array<string, mixed>>  $periods
     * @param  list<array<string, mixed>>  $applications
     * @param  list<array<string, mixed>>  $documents
     * @param  list<array{id:int, name:string}>  $gradeLevels
     * @param  list<array{id:int, name:string}>  $schools
     * @param  list<array{id:int, name:string}>  $departments
     * @param  list<array{id:int, name:string}>  $specializations
     * @param  list<array{status:int, key:string}>  $workflowSteps
     * @param  array{overall_percent:int, stages:list<array{status:int, percent:int, count:int}>}  $workflowProgress
     * @param  list<array{id:int, name:string, start_date:string, max_applications:?int, total_count:int, submitted_count:int, remaining:?int}>  $activePeriods
     * @param  array{page:int, per_page:int, total:int, total_pages:int}  $pagination
     * @param  array<int, list<int>>  $statusTransitions
     */
    public function __construct(
        public array $periods,
        public array $applications,
        public array $documents,
        public array $gradeLevels,
        public array $schools,
        public array $departments,
        public array $specializations,
        public array $workflowSteps,
        public array $workflowProgress = ['overall_percent' => 0, 'stages' => []],
        public array $activePeriods = [],
        public ?int $selectedPeriodId = null,
        public array $pagination = [
            'page' => 1,
            'per_page' => 15,
            'total' => 0,
            'total_pages' => 1,
        ],
        public array $statusTransitions = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'periods' => $this->periods,
            'applications' => $this->applications,
            'documents' => $this->documents,
            'grade_levels' => $this->gradeLevels,
            'schools' => $this->schools,
            'departments' => $this->departments,
            'specializations' => $this->specializations,
            'workflow_steps' => $this->workflowSteps !== []
                ? $this->workflowSteps
                : array_map(
                    static fn (ApplicationStatus $status): array => [
                        'status' => $status->value,
                        'key' => $status->name,
                    ],
                    ApplicationStatus::pipelineSteps(),
                ),
            'workflow_progress' => $this->workflowProgress,
            'active_periods' => $this->activePeriods,
            'selected_period_id' => $this->selectedPeriodId,
            'pagination' => $this->pagination,
            'status_transitions' => $this->statusTransitions,
        ];
    }
}
