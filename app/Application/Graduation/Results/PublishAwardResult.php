<?php

namespace App\Application\Graduation\Results;

use App\Application\Shared\Results\ApplicationResult;

/** Present for ARCH-202; PublishAward remains policy-gated (HD-38). */
final readonly class PublishAwardResult extends ApplicationResult
{
    private function __construct(bool $success)
    {
        parent::__construct($success, [], [], false);
    }

    public static function success(): self
    {
        return new self(true);
    }
}
