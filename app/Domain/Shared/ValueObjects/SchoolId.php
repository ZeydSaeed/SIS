<?php

namespace App\Domain\Shared\ValueObjects;

use App\Domain\Shared\ValueObject;
use InvalidArgumentException;

final readonly class SchoolId extends ValueObject
{
    public function __construct(
        public int $value,
    ) {
        if ($value <= 0) {
            throw new InvalidArgumentException('School id must be a positive integer.');
        }
    }

    public function toArray(): array
    {
        return ['school_id' => $this->value];
    }
}
