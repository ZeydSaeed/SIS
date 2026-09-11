<?php

namespace App\Domain\Graduation\Exceptions;

use DomainException;

final class PublicationPolicyNotConfiguredException extends DomainException
{
    public static function blocked(): self
    {
        return new self('PublishAward is policy-gated (HD-38 OPEN). Publication is not configured for this phase.');
    }
}
