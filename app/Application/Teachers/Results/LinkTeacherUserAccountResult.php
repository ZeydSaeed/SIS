<?php

namespace App\Application\Teachers\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class LinkTeacherUserAccountResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $userId = null,
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, [], [], $fromIdempotencyCache);
    }

    public static function success(?int $userId): self
    {
        return new self(true, $userId);
    }

    public static function fromIdempotency(?int $userId): self
    {
        return new self(true, $userId, true);
    }
}
