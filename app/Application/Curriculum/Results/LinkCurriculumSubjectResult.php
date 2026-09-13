<?php

namespace App\Application\Curriculum\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class LinkCurriculumSubjectResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $linkId = null,
        array $errors = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, [], $fromIdempotencyCache);
    }

    public static function success(int $linkId): self
    {
        return new self(true, $linkId);
    }

    public static function fromIdempotency(int $linkId): self
    {
        return new self(true, $linkId, fromIdempotencyCache: true);
    }

    /** @param  list<string>  $errors */
    public static function failure(array $errors): self
    {
        return new self(false, null, $errors);
    }
}
