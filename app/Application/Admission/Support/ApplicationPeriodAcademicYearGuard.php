<?php

namespace App\Application\Admission\Support;

use App\Domain\Academic\Repositories\AcademicYearRepositoryInterface;
use App\Domain\Academic\Services\AcademicYearDateWindow;
use DomainException;

final class ApplicationPeriodAcademicYearGuard
{
    public function __construct(
        private readonly AcademicYearRepositoryInterface $years,
    ) {}

    /** An open-ended period (no end date) only needs its start inside the year. */
    public function assertDatesFit(int $academicYearId, string $startDate, ?string $endDate): void
    {
        $year = $this->years->findById($academicYearId);
        if ($year === null) {
            throw new DomainException('Academic year not found.');
        }

        if (! AcademicYearDateWindow::contains($year->startDate, $year->endDate, $startDate, $endDate ?? $startDate)) {
            throw new DomainException('Application period dates must fall within the selected academic year.');
        }
    }
}
