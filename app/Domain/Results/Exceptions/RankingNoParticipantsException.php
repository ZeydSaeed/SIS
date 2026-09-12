<?php

namespace App\Domain\Results\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class RankingNoParticipantsException extends SisDomainException
{
    public static function forCohort(): self
    {
        return new self('results.ranking_no_participants');
    }
}
