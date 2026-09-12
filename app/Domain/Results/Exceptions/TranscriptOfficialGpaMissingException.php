<?php

namespace App\Domain\Results\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class TranscriptOfficialGpaMissingException extends SisDomainException
{
    public static function forIdentity(): self
    {
        return new self('results.transcript_official_gpa_missing');
    }
}
