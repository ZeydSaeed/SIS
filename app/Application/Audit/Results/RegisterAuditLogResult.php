<?php

namespace App\Application\Audit\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class RegisterAuditLogResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $auditLogId = null,
        array $errors = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, [], $fromIdempotencyCache);
    }

    public static function success(int $auditLogId): self
    {
        return new self(true, $auditLogId);
    }

    public static function fromIdempotency(int $auditLogId): self
    {
        return new self(true, $auditLogId, fromIdempotencyCache: true);
    }

    /** @param  list<string>  $errors */
    public static function failure(array $errors): self
    {
        return new self(false, null, $errors);
    }
}
