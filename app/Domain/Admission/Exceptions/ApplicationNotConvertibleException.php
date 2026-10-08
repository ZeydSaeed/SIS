<?php

namespace App\Domain\Admission\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class ApplicationNotConvertibleException extends SisDomainException
{
    public static function forStatus(int $status): self
    {
        return new self("Application status {$status} cannot be converted to a student.", 'admission.application_not_convertible');
    }
}
