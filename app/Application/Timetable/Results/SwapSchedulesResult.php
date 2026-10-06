<?php

namespace App\Application\Timetable\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class SwapSchedulesResult extends ApplicationResult
{
    /**
     * @param  list<int>  $movedScheduleIds
     * @param  list<string>  $errors
     */
    private function __construct(
        bool $success,
        public array $movedScheduleIds = [],
        array $errors = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, [], $fromIdempotencyCache);
    }

    /** @param  list<int>  $movedScheduleIds */
    public static function success(array $movedScheduleIds): self
    {
        return new self(true, $movedScheduleIds);
    }

    /** @param  list<int>  $movedScheduleIds */
    public static function fromIdempotency(array $movedScheduleIds): self
    {
        return new self(true, $movedScheduleIds, fromIdempotencyCache: true);
    }

    public static function failure(string $code): self
    {
        return new self(false, [], [$code]);
    }
}
