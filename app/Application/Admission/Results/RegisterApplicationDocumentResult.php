<?php

namespace App\Application\Admission\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class RegisterApplicationDocumentResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $documentId = null,
        array $errors = [],
        array $warnings = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, $warnings, $fromIdempotencyCache);
    }

    public static function success(int $documentId): self
    {
        return new self(true, $documentId);
    }

    public static function fromIdempotency(int $documentId): self
    {
        return new self(true, $documentId, fromIdempotencyCache: true);
    }

    /**
     * @param  list<string>  $errors
     */
    public static function failure(array $errors): self
    {
        return new self(false, errors: $errors);
    }
}
