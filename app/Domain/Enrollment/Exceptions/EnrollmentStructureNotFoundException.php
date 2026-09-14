<?php

namespace App\Domain\Enrollment\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class EnrollmentStructureNotFoundException extends SisDomainException
{
    public static function forClass(int $classId): self
    {
        return new self(
            "Enrollment class {$classId} was not found.",
            'enrollment.class_not_found',
        );
    }

    public static function forSection(int $sectionId): self
    {
        return new self(
            "Enrollment section {$sectionId} was not found.",
            'enrollment.section_not_found',
        );
    }
}
