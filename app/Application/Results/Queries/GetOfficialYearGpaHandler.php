<?php

namespace App\Application\Results\Queries;

use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Application\Results\DTOs\OfficialYearGpaDTO;
use App\Domain\Results\Repositories\GpaResultRepositoryInterface;

final class GetOfficialYearGpaHandler implements QueryHandler
{
    public function __construct(
        private readonly GpaResultRepositoryInterface $gpaResults,
    ) {}

    public function handle(Query $query): ?OfficialYearGpaDTO
    {
        assert($query instanceof GetOfficialYearGpaQuery);

        $row = $this->gpaResults->findOfficialYearGpaRead(
            $query->schoolId,
            $query->enrollmentId,
            $query->academicYearId,
        );

        if ($row === null) {
            return null;
        }

        return new OfficialYearGpaDTO(
            schoolId: $query->schoolId,
            enrollmentId: $query->enrollmentId,
            academicYearId: $query->academicYearId,
            gpaResultId: $row->id,
            resultVersion: $row->resultVersion,
            gpaValue: $row->gpaValue,
            scaleCode: $row->scaleCode,
            incomplete: $row->incomplete,
            sourceFingerprint: $row->sourceFingerprint,
        );
    }
}
