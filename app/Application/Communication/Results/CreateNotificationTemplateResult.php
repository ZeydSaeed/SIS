<?php

namespace App\Application\Communication\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class CreateNotificationTemplateResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $templateId = null,
        array $errors = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, [], $fromIdempotencyCache);
    }

    public static function success(int $templateId): self
    {
        return new self(true, $templateId);
    }

    public static function fromIdempotency(int $templateId): self
    {
        return new self(true, $templateId, fromIdempotencyCache: true);
    }

    /** @param  list<string>  $errors */
    public static function failure(array $errors): self
    {
        return new self(false, null, $errors);
    }
}
