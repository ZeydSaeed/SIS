<?php

namespace App\Application\Timetable\Results;

use App\Application\Shared\Results\ApplicationResult;

/**
 * Outcome of a timetable engine command: the id it created / touched and any data the page needs.
 * Each command has its own named subclass (feature contract) sharing this shape.
 */
abstract readonly class TimetableEngineResult extends ApplicationResult
{
    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $errors
     */
    final public function __construct(
        bool $success,
        public ?int $id = null,
        public array $data = [],
        array $errors = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, [], $fromIdempotencyCache);
    }

    /** @param  array<string, mixed>  $data */
    public static function success(?int $id = null, array $data = []): static
    {
        return new static(true, $id, $data);
    }

    public static function failure(string ...$codes): static
    {
        return new static(false, null, [], array_values($codes));
    }

    /** @param  array{id?: int|null, data?: array<string, mixed>}  $payload */
    public static function cached(array $payload): static
    {
        return new static(true, $payload['id'] ?? null, $payload['data'] ?? [], [], true);
    }

    /** @return array{id: int|null, data: array<string, mixed>} */
    public function payload(): array
    {
        return ['id' => $this->id, 'data' => $this->data];
    }
}
