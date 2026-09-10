<?php

namespace App\Database\Exceptions;

use RuntimeException;

final class ProtectedDatabaseException extends RuntimeException
{
    public static function destructiveBlocked(string $database, string $reason): self
    {
        return new self(
            "Destructive database operation blocked for [{$database}]: {$reason}"
        );
    }
}
