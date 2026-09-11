<?php

namespace App\Application\Graduation\Queries;

use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Application\Graduation\Contracts\GraduationAuthorityPort;
use App\Application\Graduation\Contracts\GraduationReadRepositoryInterface;
use App\Application\Graduation\DTOs\GraduationApprovalsDTO;
use App\Domain\Graduation\Exceptions\CompletionOutcomeNotFoundException;

final class GetGraduationApprovalHandler implements QueryHandler
{
    public function __construct(
        private readonly GraduationReadRepositoryInterface $graduation,
        private readonly GraduationAuthorityPort $authority,
    ) {}

    public function handle(Query $query): GraduationApprovalsDTO
    {
        assert($query instanceof GetGraduationApprovalQuery);

        $this->authority->assertSchoolMatches($query->schoolId);

        $result = $this->graduation->findGraduationApprovals($query->schoolId, $query->enrollmentId);
        if ($result === null) {
            throw CompletionOutcomeNotFoundException::forEnrollment($query->schoolId, $query->enrollmentId);
        }

        return $result;
    }
}
