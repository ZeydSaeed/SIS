<?php

namespace App\Application\Admission\Queries;

use App\Application\Admission\Contracts\AdmissionReadRepositoryInterface;
use App\Application\Admission\DTOs\AdmissionWorkspaceDTO;
use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;

final class GetAdmissionWorkspaceHandler implements QueryHandler
{
    public function __construct(
        private readonly AdmissionReadRepositoryInterface $admission,
    ) {}

    public function handle(Query $query): AdmissionWorkspaceDTO
    {
        assert($query instanceof GetAdmissionWorkspaceQuery);

        $workspace = $this->admission->workspace($query->schoolId, $query->academicYearId);

        return new AdmissionWorkspaceDTO(
            periods: $workspace['periods'],
            applications: $workspace['applications'],
            documents: $workspace['documents'],
            gradeLevels: $workspace['grade_levels'],
            schools: $workspace['schools'],
            departments: $workspace['departments'],
            specializations: $workspace['specializations'],
            workflowSteps: $workspace['workflow_steps'],
        );
    }
}
