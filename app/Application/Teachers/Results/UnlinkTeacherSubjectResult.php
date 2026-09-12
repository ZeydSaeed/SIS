<?php

namespace App\Application\Teachers\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class UnlinkTeacherSubjectResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public bool $wasPresent = false,
        array $errors = [],
    ) {
        parent::__construct($success, $errors);
    }

    public static function success(bool $wasPresent): self
    {
        return new self(true, $wasPresent);
    }

    /** @param  list<string>  $errors */
    public static function failure(array $errors): self
    {
        return new self(false, false, $errors);
    }
}
