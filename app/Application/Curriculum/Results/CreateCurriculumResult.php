<?php

namespace App\Application\Curriculum\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class CreateCurriculumResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $curriculumId = null,
        array $errors = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, [], $fromIdempotencyCache);
    }

    public static function success(int $curriculumId): self
    {
        return new self(true, $curriculumId);
    }

    public static function fromIdempotency(int $curriculumId): self
    {
        return new self(true, $curriculumId, fromIdempotencyCache: true);
    }

    /** @param  list<string>  $errors */
    public static function failure(array $errors): self
    {
        return new self(false, null, $errors);
    }
}
