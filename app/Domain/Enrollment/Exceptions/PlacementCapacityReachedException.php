<?php

namespace App\Domain\Enrollment\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class PlacementCapacityReachedException extends SisDomainException
{
    public static function forPlacement(int $classId, int $sectionId): self
    {
        return new self(
            "Class {$classId} / section {$sectionId} has reached its capacity.",
            'enrollment.capacity_reached',
        );
    }
}
