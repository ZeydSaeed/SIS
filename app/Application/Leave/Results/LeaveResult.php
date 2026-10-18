<?php

namespace App\Application\Leave\Results;

use App\Application\Shared\Results\ApplicationResult;

/**
 * Shared shape of the leave write results: a small payload (ids, number, status, counts). Rule failures are thrown
 * as LeaveRuleViolation (shown to the user as a localized message), so a result is always a success.
 */
abstract readonly class LeaveResult extends ApplicationResult
{
    /**
     * @param  array<string, mixed>  $data
     */
    final protected function __construct(
        bool $success,
        public array $data = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, [], [], $fromIdempotencyCache);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function success(array $data): static
    {
        return new static(true, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromIdempotency(array $data): static
    {
        return new static(true, $data, fromIdempotencyCache: true);
    }

    public function get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }
}
