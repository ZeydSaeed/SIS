<?php

namespace App\Domain\Graduation\Exceptions;

use DomainException;

final class GraduationAuthorityDeniedException extends DomainException
{
    public static function forAction(string $action): self
    {
        return new self("Graduation authority denied for action [{$action}].");
    }

    public static function schoolMismatch(): self
    {
        return new self('Graduation command school_id does not match active school context.');
    }

    public static function catalogNotConfigured(): self
    {
        return new self('Graduation authority catalog is fail-closed until HD-31-G permission mapping is locked.');
    }
}
