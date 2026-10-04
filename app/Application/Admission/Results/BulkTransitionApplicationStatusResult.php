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
        /** @var list<int> Students created when the transition accepted (and converted) applications. */
        public array $convertedStudentIds = [],
    ) {
        parent::__construct($success, $errors, $warnings, $fromIdempotencyCache);
    }

    /**
     * @param  list<int>  $applicationIds
     * @param  list<int>  $convertedStudentIds
     */
    public static function success(array $applicationIds, int $toStatus, array $convertedStudentIds = []): self
    {
        return new self(true, $applicationIds, $toStatus, count($applicationIds), convertedStudentIds: $convertedStudentIds);
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
