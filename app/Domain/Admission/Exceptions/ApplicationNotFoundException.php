<?php

namespace App\Domain\Admission\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class ApplicationNotFoundException extends SisDomainException
{
    public static function forId(int $id): self
    {
        return new self("Admission application {$id} was not found.", 'admission.application_missing');
    }
}
