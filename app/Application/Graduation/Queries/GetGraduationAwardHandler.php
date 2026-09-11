<?php

namespace App\Application\Graduation\Queries;

use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Application\Graduation\Contracts\GraduationAuthorityPort;
use App\Application\Graduation\Contracts\GraduationReadRepositoryInterface;
use App\Application\Graduation\DTOs\GraduationAwardDTO;

final class GetGraduationAwardHandler implements QueryHandler
{
    public function __construct(
        private readonly GraduationReadRepositoryInterface $graduation,
        private readonly GraduationAuthorityPort $authority,
    ) {}

    public function handle(Query $query): ?GraduationAwardDTO
    {
        assert($query instanceof GetGraduationAwardQuery);

        $this->authority->assertSchoolMatches($query->schoolId);

        return $this->graduation->findGraduationAward($query->schoolId, $query->enrollmentId);
    }
}
