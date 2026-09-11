<?php

namespace App\Application\Graduation\Queries;

use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Application\Graduation\Contracts\GraduationAuthorityPort;
use App\Application\Graduation\Contracts\GraduationReadRepositoryInterface;
use App\Application\Graduation\DTOs\CompletionStatusDTO;
use App\Domain\Graduation\Exceptions\CompletionOutcomeNotFoundException;

final class GetCompletionStatusHandler implements QueryHandler
{
    public function __construct(
        private readonly GraduationReadRepositoryInterface $graduation,
        private readonly GraduationAuthorityPort $authority,
    ) {}

    public function handle(Query $query): CompletionStatusDTO
    {
        assert($query instanceof GetCompletionStatusQuery);

        $this->authority->assertSchoolMatches($query->schoolId);

        $status = $this->graduation->findCompletionStatus($query->schoolId, $query->enrollmentId);
        if ($status === null) {
            throw CompletionOutcomeNotFoundException::forEnrollment($query->schoolId, $query->enrollmentId);
        }

        return $status;
    }
}
