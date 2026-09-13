<?php

namespace App\Application\Communication\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class CancelNotificationJobResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $notificationJobId = null,
        array $errors = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, [], $fromIdempotencyCache);
    }

    public static function success(int $notificationJobId): self
    {
        return new self(true, $notificationJobId);
    }

    public static function fromIdempotency(int $notificationJobId): self
    {
        return new self(true, $notificationJobId, fromIdempotencyCache: true);
    }

    /** @param  list<string>  $errors */
    public static function failure(array $errors): self
    {
        return new self(false, null, $errors);
    }
}
