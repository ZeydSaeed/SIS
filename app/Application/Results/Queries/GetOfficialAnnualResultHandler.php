<?php

namespace App\Application\Results\Queries;

use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Application\Results\DTOs\OfficialAnnualResultDTO;
use App\Domain\Results\Repositories\AnnualResultRepositoryInterface;

final class GetOfficialAnnualResultHandler implements QueryHandler
{
    public function __construct(
        private readonly AnnualResultRepositoryInterface $annualResults,
    ) {}

    public function handle(Query $query): ?OfficialAnnualResultDTO
    {
        assert($query instanceof GetOfficialAnnualResultQuery);

        $row = $this->annualResults->findCurrentOfficial(
            $query->schoolId,
            $query->enrollmentId,
            $query->academicYearId,
        );

        if ($row === null) {
            return null;
        }

        return new OfficialAnnualResultDTO(
            schoolId: $query->schoolId,
            enrollmentId: $query->enrollmentId,
            academicYearId: $query->academicYearId,
            annualResultId: $row->id,
            resultVersion: $row->resultVersion,
            averageWeightedTotal: $row->averageWeightedTotal,
            incomplete: $row->incomplete,
            sourceFingerprint: $row->sourceFingerprint,
        );
    }
}
