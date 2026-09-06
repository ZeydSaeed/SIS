<?php

namespace App\Application\Contracts;

interface IdempotencyStore
{
    /**
     * @return array<string, mixed>|null
     */
    public function find(string $key, string $commandName): ?array;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function store(string $key, string $commandName, array $payload, int $ttlSeconds = 86400): void;
}
