<?php

namespace App\Application\Enrollment\Results;

use App\Application\Shared\Results\ApplicationResult;

/** Base for create / update of a class (الصف) or section (الشعبة). */
abstract readonly class SaveEnrollmentStructureResult extends ApplicationResult
{
    /** @param  list<string>  $errors */
    final protected function __construct(
        bool $success,
        public ?int $id = null,
        array $errors = [],
        bool $fromIdempotencyCache = false,
        array $warnings = [],
    ) {
        parent::__construct($success, $errors, $warnings, $fromIdempotencyCache);
    }

    /** @param  list<string>  $warnings */
    public static function success(int $id, array $warnings = []): static
    {
        return new static(true, $id, warnings: $warnings);
    }

    public static function fromIdempotency(int $id): static
    {
        return new static(true, $id, fromIdempotencyCache: true);
    }

    public static function failure(string $code): static
    {
        return new static(false, null, [$code]);
    }
}
