<?php

namespace App\Application\Curriculum\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class UpdateCurriculumSpecializationResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $curriculumId = null,
        public ?int $specializationId = null,
        array $errors = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, [], $fromIdempotencyCache);
    }

    public static function success(int $curriculumId, ?int $specializationId): self
    {
        return new self(true, $curriculumId, $specializationId);
    }

    public static function fromIdempotency(int $curriculumId, ?int $specializationId): self
    {
        return new self(true, $curriculumId, $specializationId, fromIdempotencyCache: true);
    }

    /** @param  list<string>  $errors */
    public static function failure(array $errors): self
    {
        return new self(false, null, null, $errors);
    }
}
