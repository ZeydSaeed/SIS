<?php

namespace App\Application\Teachers\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class EndTeachingAssignmentResult extends ApplicationResult
{
    /** @param  list<string>  $errors */
    private function __construct(
        bool $success,
        public ?int $assignmentId = null,
        array $errors = [],
    ) {
        parent::__construct($success, $errors);
    }

    public static function success(int $assignmentId): self
    {
        return new self(true, $assignmentId);
    }

    public static function failure(string $code): self
    {
        return new self(false, null, [$code]);
    }
}
