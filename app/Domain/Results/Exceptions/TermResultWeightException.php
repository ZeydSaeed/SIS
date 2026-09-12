<?php

namespace App\Domain\Results\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class TermResultWeightException extends SisDomainException
{
    public static function sumNotOneHundred(string $sum): self
    {
        return new self("results.term_weight_sum_invalid: contributing weights sum to {$sum}, expected 100");
    }
}
