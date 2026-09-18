<?php

namespace App\Domain\Admission\Exceptions;

use DomainException;

final class ApplicationNotConvertibleException extends DomainException
{
    public static function forStatus(int $status): self
    {
        return new self("Application status {$status} cannot be converted to a student.");
    }
}
