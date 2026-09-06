<?php

namespace App\Domain\Shared;

abstract readonly class ValueObject
{
    /**
     * @return array<string, mixed>
     */
    abstract public function toArray(): array;
}
