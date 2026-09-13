<?php

namespace App\Application\Documents\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class UploadDocumentResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $documentId = null,
        public ?string $storageKey = null,
        array $errors = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, [], $fromIdempotencyCache);
    }

    public static function success(int $documentId, string $storageKey): self
    {
        return new self(true, $documentId, $storageKey);
    }

    public static function fromIdempotency(int $documentId, string $storageKey): self
    {
        return new self(true, $documentId, $storageKey, fromIdempotencyCache: true);
    }

    /** @param  list<string>  $errors */
    public static function failure(array $errors): self
    {
        return new self(false, null, null, $errors);
    }
}
