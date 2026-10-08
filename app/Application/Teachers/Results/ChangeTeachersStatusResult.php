<?php

namespace App\Application\Teachers\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class ChangeTeachersStatusResult extends ApplicationResult
{
    /**
     * @param  list<int>  $teacherIds
     * @param  list<string>  $errors
     */
    private function __construct(
        bool $success,
        public array $teacherIds = [],
        array $errors = [],
        bool $fromIdempotencyCache = false,
        array $warnings = [],
    ) {
        parent::__construct($success, $errors, $warnings, $fromIdempotencyCache);
    }

    /** @param  list<int>  $teacherIds */
    public static function success(array $teacherIds, bool $fromIdempotencyCache = false, array $warnings = []): self
    {
        return new self(true, $teacherIds, [], $fromIdempotencyCache, $warnings);
    }

    public static function failure(string $code): self
    {
        return new self(false, [], [$code]);
    }
}
