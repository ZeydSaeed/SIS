<?php

namespace App\Application\Graduation\Queries;

use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Application\Graduation\Contracts\GraduationAuthorityPort;
use App\Application\Graduation\Contracts\GraduationReadRepositoryInterface;
use App\Application\Graduation\DTOs\OutcomeHistoryDTO;

final class GetOutcomeHistoryHandler implements QueryHandler
{
    public function __construct(
        private readonly GraduationReadRepositoryInterface $graduation,
        private readonly GraduationAuthorityPort $authority,
    ) {}

    public function handle(Query $query): OutcomeHistoryDTO
    {
        assert($query instanceof GetOutcomeHistoryQuery);

        $this->authority->assertSchoolMatches($query->schoolId);

        return $this->graduation->findOutcomeHistory($query->schoolId, $query->enrollmentId);
    }
}
