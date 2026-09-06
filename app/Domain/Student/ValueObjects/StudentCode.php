<?php

namespace App\Domain\Student\ValueObjects;

use App\Domain\Shared\ValueObject;
use InvalidArgumentException;

final readonly class StudentCode extends ValueObject
{
    public function __construct(
        public string $value,
    ) {
        if ($value === '' || strlen($value) > 50) {
            throw new InvalidArgumentException('Student code must be 1–50 characters.');
        }
    }

    public function toArray(): array
    {
        return ['student_code' => $this->value];
    }
}
