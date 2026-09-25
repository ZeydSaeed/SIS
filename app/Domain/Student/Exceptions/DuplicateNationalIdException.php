<?php

namespace App\Domain\Student\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class DuplicateNationalIdException extends SisDomainException
{
    public static function forNationalId(string $nationalId): self
    {
        return new self(
            "الرقم الوطني {$nationalId} مسجّل مسبقاً لطالب موجود.",
            'student.national_id_exists',
        );
    }

    public static function forNationalIdInOtherSchool(string $nationalId): self
    {
        return new self(
            "الرقم الوطني {$nationalId} مسجّل مسبقاً لطالب في مدرسة أخرى.",
            'student.national_id_exists',
        );
    }
}
