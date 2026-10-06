<?php

namespace App\Application\Timetable\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class UpdatePeriodResult extends ApplicationResult
{
    /** @param  list<string>  $errors */
    private function __construct(
        bool $success,
        public ?int $periodId = null,
        array $errors = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, [], $fromIdempotencyCache);
    }

    public static function success(int $periodId): self
    {
        return new self(true, $periodId);
    }

    public static function fromIdempotency(int $periodId): self
    {
        return new self(true, $periodId, fromIdempotencyCache: true);
    }

    public static function failure(string $code): self
    {
        return new self(false, null, [$code]);
    }
}
