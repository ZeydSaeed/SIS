<?php

namespace App\Application\Teachers\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class SetTeacherPrimarySchoolResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $teacherId = null,
        array $errors = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, [], $fromIdempotencyCache);
    }

    public static function success(int $teacherId): self
    {
        return new self(true, $teacherId);
    }

    public static function fromIdempotency(int $teacherId): self
    {
        return new self(true, $teacherId, fromIdempotencyCache: true);
    }

    /** @param  list<string>  $errors */
    public static function failure(array $errors): self
    {
        return new self(false, null, $errors);
    }
}
