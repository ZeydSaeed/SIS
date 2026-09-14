<?php

namespace App\Application\Results\Queries;

use App\Application\Results\DTOs\OfficialTermResultListItemDTO;
use App\Domain\Results\Repositories\AnnualResultRepositoryInterface;

final class ListOfficialTermResultsHandler
{
    public function __construct(
        private readonly AnnualResultRepositoryInterface $annualResults,
    ) {}

    /**
     * @return list<OfficialTermResultListItemDTO>
     */
    public function handle(ListOfficialTermResultsQuery $query): array
    {
        return array_map(
            static fn ($row): OfficialTermResultListItemDTO => new OfficialTermResultListItemDTO(
                schoolId: $query->schoolId,
                enrollmentId: $query->enrollmentId,
                academicYearId: $query->academicYearId,
                termResultId: $row->termResultId,
                termId: $row->termId,
                subjectId: $row->subjectId,
                weightedTotal: $row->weightedTotal,
                passFail: $row->passFail,
                incomplete: $row->incomplete,
            ),
            $this->annualResults->listCurrentOfficialTermRows(
                $query->schoolId,
                $query->enrollmentId,
                $query->academicYearId,
            ),
        );
    }
}
