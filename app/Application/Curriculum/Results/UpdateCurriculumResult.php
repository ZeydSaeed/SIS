<?php

namespace App\Application\Curriculum\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class UpdateCurriculumResult extends ApplicationResult
{
    /**
     * @param  array{name?: string, specialization_id?: ?int}|null  $fields
     */
    private function __construct(
        bool $success,
        public ?int $curriculumId = null,
        public ?array $fields = null,
        array $errors = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, [], $fromIdempotencyCache);
    }

    /** @param  array{name?: string, specialization_id?: ?int}  $fields */
    public static function success(int $curriculumId, array $fields): self
    {
        return new self(true, $curriculumId, $fields);
    }

    /** @param  array{name?: string, specialization_id?: ?int}  $fields */
    public static function fromIdempotency(int $curriculumId, array $fields): self
    {
        return new self(true, $curriculumId, $fields, fromIdempotencyCache: true);
    }

    /** @param  list<string>  $errors */
    public static function failure(array $errors): self
    {
        return new self(false, null, null, $errors);
    }
}
