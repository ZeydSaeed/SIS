<?php

namespace App\Domain\Graduation\Exceptions;

use DomainException;

final class EvaluatorApproverConflictException extends DomainException
{
    public static function sameActor(): self
    {
        return new self('Evaluator must not approve the same Graduation outcome.');
    }
}
