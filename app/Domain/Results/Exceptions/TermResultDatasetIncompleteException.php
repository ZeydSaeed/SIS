<?php

namespace App\Domain\Results\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class TermResultDatasetIncompleteException extends SisDomainException
{
    public static function missingFinalizedGrades(): self
    {
        return new self('results.official_dataset_incomplete');
    }
}
