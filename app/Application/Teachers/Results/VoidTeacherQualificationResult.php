<?php

namespace App\Application\Teachers\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class VoidTeacherQualificationResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $qualificationId = null,
        array $errors = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, [], $fromIdempotencyCache);
    }

    public static function success(int $qualificationId): self
    {
        return new self(true, $qualificationId);
    }

    public static function fromIdempotency(int $qualificationId): self
    {
        return new self(true, $qualificationId, fromIdempotencyCache: true);
    }

    /** @param  list<string>  $errors */
    public static function failure(array $errors): self
    {
        return new self(false, null, $errors);
    }
}
