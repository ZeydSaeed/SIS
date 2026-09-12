<?php

namespace App\Application\Results\Queries;

use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Application\Results\DTOs\OfficialTermResultDTO;
use App\Domain\Results\Repositories\TermResultRepositoryInterface;

final class GetOfficialTermResultHandler implements QueryHandler
{
    public function __construct(
        private readonly TermResultRepositoryInterface $termResults,
    ) {}

    public function handle(Query $query): ?OfficialTermResultDTO
    {
        assert($query instanceof GetOfficialTermResultQuery);

        $row = $this->termResults->findCurrentOfficial(
            $query->schoolId,
            $query->enrollmentId,
            $query->termId,
            $query->subjectId,
        );

        if ($row === null) {
            return null;
        }

        return new OfficialTermResultDTO(
            schoolId: $query->schoolId,
            enrollmentId: $query->enrollmentId,
            academicYearId: $query->academicYearId,
            termId: $query->termId,
            subjectId: $query->subjectId,
            termResultId: $row->id,
            resultVersion: $row->resultVersion,
            weightedTotal: $row->weightedTotal,
            incomplete: $row->incomplete,
            sourceFingerprint: $row->sourceFingerprint,
        );
    }
}
