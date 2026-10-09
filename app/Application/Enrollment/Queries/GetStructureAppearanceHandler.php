<?php

namespace App\Application\Enrollment\Queries;

use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Domain\Enrollment\Repositories\EnrollmentStructureRepositoryInterface;

final class GetStructureAppearanceHandler implements QueryHandler
{
    public function __construct(
        private readonly EnrollmentStructureRepositoryInterface $structure,
    ) {}

    /** @return array{classes: array<int, array{abbreviation: string|null, color_hue: int|null}>, sections: array<int, array{abbreviation: string|null, color_hue: int|null}>} */
    public function handle(Query $query): array
    {
        assert($query instanceof GetStructureAppearanceQuery);

        return $this->structure->appearanceForYear($query->schoolId, $query->academicYearId);
    }
}
