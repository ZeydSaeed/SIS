<?php

namespace App\Application\Shared\Results;

abstract readonly class ApplicationResult
{
    /**
     * @param  list<string>  $errors
     * @param  list<string>  $warnings
     */
    protected function __construct(
        public bool $success,
        public array $errors = [],
        public array $warnings = [],
        public bool $fromIdempotencyCache = false,
    ) {}

    public function failed(): bool
    {
        return ! $this->success;
    }
}
