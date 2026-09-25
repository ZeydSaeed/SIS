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
     * @param  list<array{id:int, name:string}>  $branches
     * @param  list<array{id:int, branch_id:int|null, name:string}>  $departments
     * @param  list<array{id:int, department_id:int|null, name:string}>  $specializations
     * @param  list<array{status:int, key:string}>  $workflowSteps
     * @param  array{overall_percent:int, stages:list<array{status:int, percent:int, count:int}>}  $workflowProgress
     * @param  list<array{id:int, name:string, start_date:string, max_applications:?int, total_count:int, submitted_count:int, remaining:?int}>  $activePeriods
     * @param  array{page:int, per_page:int, total:int, total_pages:int}  $pagination
     * @param  array<int, list<int>>  $statusTransitions
     * @param  list<array{id:int, full_name:string, academic_year_name:string, period_name:string, academic_year_id:int, period_id:int, request_kind:int}>  $acceptedStudents
     * @param  array<int, array{total:int, submitted:int}>  $periodCounts
     */
    public function __construct(
        public array $periods,
        public array $applications,
        public array $documents,
        public array $gradeLevels,
        public array $schools,
        public array $branches,
        public array $departments,
        public array $specializations,
        public array $workflowSteps,
        public array $workflowProgress = ['overall_percent' => 0, 'stages' => []],
        public array $activePeriods = [],
        public ?int $selectedPeriodId = null,
        public array $pagination = [
            'page' => 1,
            'per_page' => 17,
            'total' => 0,
            'total_pages' => 1,
        ],
        public array $statusTransitions = [],
        public array $acceptedStudents = [],
        public array $periodCounts = [],
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
            'branches' => $this->branches,
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
            'accepted_students' => $this->acceptedStudents,
            'period_counts' => $this->periodCounts,
        ];
    }
}
