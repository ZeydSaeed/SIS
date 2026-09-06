<?php

namespace App\Domain\Shared\ValueObjects;

use App\Domain\Shared\ValueObject;
use InvalidArgumentException;

final readonly class AcademicYearId extends ValueObject
{
    public function __construct(
        public int $value,
    ) {
        if ($value <= 0) {
            throw new InvalidArgumentException('Academic year id must be a positive integer.');
        }
    }

    public function toArray(): array
    {
        return ['academic_year_id' => $this->value];
    }
}
