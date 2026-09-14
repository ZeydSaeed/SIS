<?php

namespace App\Http\Support;

use App\Domain\Academic\Repositories\AcademicYearRepositoryInterface;

final class AcademicYearContextResolver
{
    public function __construct(
        private readonly AcademicYearRepositoryInterface $years,
    ) {}

    public function resolve(?int $requestedId): ?int
    {
        if ($requestedId !== null && $requestedId >= 1) {
            return $this->years->findById($requestedId)?->id;
        }

        $fallback = null;
        foreach ($this->years->listAll() as $year) {
            if ($year->isCurrent) {
                return $year->id;
            }
            $fallback ??= $year->id;
        }

        return $fallback;
    }
}
