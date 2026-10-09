<?php

namespace App\Application\Timetable\Results;

use App\Application\Shared\Results\ApplicationResult;

/** «الاستراحات وإعدادات الحصص»: the number of periods in the re-timed day. */
final readonly class ReshapeSchoolDayResult extends ApplicationResult
{
    /** @param  list<string>  $errors */
    private function __construct(
        bool $success,
        public int $periods = 0,
        array $errors = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, [], $fromIdempotencyCache);
    }

    public static function success(int $periods): self
    {
        return new self(true, $periods);
    }

    public static function fromIdempotency(int $periods): self
    {
        return new self(true, $periods, fromIdempotencyCache: true);
    }

    public static function failure(string $code): self
    {
        return new self(false, errors: [$code]);
    }
}
