<?php

namespace App\Application\Admission\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class BulkTransitionApplicationStatusResult extends ApplicationResult
{
    /**
     * @param  list<int>  $applicationIds
     */
    private function __construct(
        bool $success,
        public array $applicationIds = [],
        public ?int $toStatus = null,
        public int $count = 0,
        array $errors = [],
        array $warnings = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, $warnings, $fromIdempotencyCache);
    }

    /**
     * @param  list<int>  $applicationIds
     */
    public static function success(array $applicationIds, int $toStatus): self
    {
        return new self(true, $applicationIds, $toStatus, count($applicationIds));
    }

    /**
     * @param  list<int>  $applicationIds
     */
    public static function fromIdempotency(array $applicationIds, int $toStatus): self
    {
        return new self(true, $applicationIds, $toStatus, count($applicationIds), fromIdempotencyCache: true);
    }

    /**
     * @param  list<string>  $errors
     */
    public static function failure(array $errors): self
    {
        return new self(false, errors: $errors);
    }
}
