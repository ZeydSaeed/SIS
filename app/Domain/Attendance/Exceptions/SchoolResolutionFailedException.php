<?php

namespace App\Domain\Attendance\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class SchoolResolutionFailedException extends SisDomainException
{
    public static function forSection(int $sectionId): self
    {
        return new self(
            "Could not resolve school for section {$sectionId}.",
            'attendance.school_resolution_failed',
        );
    }
}
