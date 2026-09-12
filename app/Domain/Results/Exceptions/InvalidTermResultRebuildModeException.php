<?php

namespace App\Domain\Results\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class InvalidTermResultRebuildModeException extends SisDomainException
{
    public static function forValue(string $mode): self
    {
        return new self("results.invalid_rebuild_mode:{$mode}");
    }
}
