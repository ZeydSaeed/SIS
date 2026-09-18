<?php

namespace App\Domain\Admission\Exceptions;

use DomainException;

final class ApplicationNotFoundException extends DomainException
{
    public static function forId(int $id): self
    {
        return new self("Admission application {$id} was not found.");
    }
}
