<?php

namespace App\Domain\Timetable\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class MissingScheduleIdempotencyKeyException extends SisDomainException
{
    public static function required(): self
    {
        return new self('timetable.schedule_idempotency_key_required');
    }
}
