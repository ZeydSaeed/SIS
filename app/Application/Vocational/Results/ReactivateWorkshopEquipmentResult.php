<?php

namespace App\Application\Vocational\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class ReactivateWorkshopEquipmentResult extends ApplicationResult
{
    private function __construct(
        bool $success,
        public ?int $equipmentId = null,
        array $errors = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, [], $fromIdempotencyCache);
    }

    public static function success(int $equipmentId): self
    {
        return new self(true, $equipmentId);
    }

    public static function fromIdempotency(int $equipmentId): self
    {
        return new self(true, $equipmentId, fromIdempotencyCache: true);
    }

    /** @param  list<string>  $errors */
    public static function failure(array $errors): self
    {
        return new self(false, null, $errors);
    }
}
