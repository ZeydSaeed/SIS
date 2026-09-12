<?php

namespace App\Application\Portal\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class LinkPortalPartyScopeResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $scopeRowId = null,
        array $errors = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, [], $fromIdempotencyCache);
    }

    public static function success(int $scopeRowId): self
    {
        return new self(true, $scopeRowId);
    }

    public static function fromIdempotency(int $scopeRowId): self
    {
        return new self(true, $scopeRowId, fromIdempotencyCache: true);
    }

    /**
     * @param  list<string>  $errors
     */
    public static function failure(array $errors): self
    {
        return new self(false, null, $errors);
    }
}
