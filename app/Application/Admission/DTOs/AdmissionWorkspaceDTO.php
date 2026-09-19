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
        ];
    }
}
