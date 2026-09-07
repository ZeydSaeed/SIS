<?php

namespace App\Domain\Student\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class StudentCodeAlreadyExistsException extends SisDomainException
{
    public static function forCode(string $code): self
    {
        return new self("Student code {$code} is already in use.", 'student.code_exists');
    }
}
