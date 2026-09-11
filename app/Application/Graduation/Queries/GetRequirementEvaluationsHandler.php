<?php

namespace App\Application\Graduation\Queries;

use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Application\Graduation\Contracts\GraduationAuthorityPort;
use App\Application\Graduation\Contracts\GraduationReadRepositoryInterface;
use App\Application\Graduation\DTOs\RequirementEvaluationsDTO;
use App\Domain\Graduation\Exceptions\CompletionOutcomeNotFoundException;

final class GetRequirementEvaluationsHandler implements QueryHandler
{
    public function __construct(
        private readonly GraduationReadRepositoryInterface $graduation,
        private readonly GraduationAuthorityPort $authority,
    ) {}

    public function handle(Query $query): RequirementEvaluationsDTO
    {
        assert($query instanceof GetRequirementEvaluationsQuery);

        $this->authority->assertSchoolMatches($query->schoolId);

        $result = $this->graduation->findRequirementEvaluations($query->schoolId, $query->enrollmentId);
        if ($result === null) {
            throw CompletionOutcomeNotFoundException::forEnrollment($query->schoolId, $query->enrollmentId);
        }

        return $result;
    }
}
