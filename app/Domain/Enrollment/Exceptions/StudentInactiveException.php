<?php

namespace App\Domain\Enrollment\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class StudentInactiveException extends SisDomainException
{
    /**
     * @param  list<string>  $reasons
     */
    public static function withReasons(array $reasons): self
    {
        return new self(
            'Student is not eligible for enrollment: '.implode('; ', $reasons),
            'enrollment.student_ineligible',
        );
    }
}
