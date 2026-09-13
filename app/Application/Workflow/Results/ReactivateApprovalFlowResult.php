<?php

namespace App\Application\Workflow\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class ReactivateApprovalFlowResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $flowId = null,
        array $errors = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, [], $fromIdempotencyCache);
    }

    public static function success(int $flowId): self
    {
        return new self(true, $flowId);
    }

    public static function fromIdempotency(int $flowId): self
    {
        return new self(true, $flowId, fromIdempotencyCache: true);
    }

    /** @param  list<string>  $errors */
    public static function failure(array $errors): self
    {
        return new self(false, null, $errors);
    }
}
