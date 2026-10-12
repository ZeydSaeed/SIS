<?php

namespace App\Application\Attendance\Results;

use App\Application\Shared\Results\ApplicationResult;

/**
 * Shared shape of the attendance workflow results: a small data payload (ids, version, counts) the HTTP layer
 * returns to the client — the client only shows success once this comes back.
 */
abstract readonly class AttendanceWorkflowResult extends ApplicationResult
{
    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $warnings
     */
    final protected function __construct(
        bool $success,
        public array $data = [],
        array $errors = [],
        array $warnings = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, $warnings, $fromIdempotencyCache);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $warnings
     */
    public static function success(array $data, array $warnings = []): static
    {
        return new static(true, $data, [], $warnings);
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
