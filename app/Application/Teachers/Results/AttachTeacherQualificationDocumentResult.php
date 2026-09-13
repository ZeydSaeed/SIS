<?php

namespace App\Application\Teachers\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class AttachTeacherQualificationDocumentResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $qualificationId = null,
        public ?string $storageKey = null,
        array $errors = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, [], $fromIdempotencyCache);
    }

    public static function success(int $qualificationId, string $storageKey): self
    {
        return new self(true, $qualificationId, $storageKey);
    }

    public static function fromIdempotency(int $qualificationId, string $storageKey): self
    {
        return new self(true, $qualificationId, $storageKey, fromIdempotencyCache: true);
    }

    /** @param  list<string>  $errors */
    public static function failure(array $errors): self
    {
        return new self(false, null, null, $errors);
    }
}
