<?php

namespace App\Application\Promotion\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class CreatePromotionRuleResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $ruleId = null,
        array $errors = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, [], $fromIdempotencyCache);
    }

    public static function success(int $ruleId): self
    {
        return new self(true, $ruleId);
    }

    public static function fromIdempotency(int $ruleId): self
    {
        return new self(true, $ruleId, fromIdempotencyCache: true);
    }

    /** @param  list<string>  $errors */
    public static function failure(array $errors): self
    {
        return new self(false, null, $errors);
    }
}
