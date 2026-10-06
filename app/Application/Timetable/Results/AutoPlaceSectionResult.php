<?php

namespace App\Application\Timetable\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class AutoPlaceSectionResult extends ApplicationResult
{
    /** @param  list<string>  $errors */
    private function __construct(
        bool $success,
        public int $placed = 0,
        public int $unplaced = 0,
        array $errors = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, [], $fromIdempotencyCache);
    }

    public static function success(int $placed, int $unplaced): self
    {
        return new self(true, $placed, $unplaced);
    }

    public static function fromIdempotency(int $placed, int $unplaced): self
    {
        return new self(true, $placed, $unplaced, fromIdempotencyCache: true);
    }

    public static function failure(string $code): self
    {
        return new self(false, errors: [$code]);
    }
}
