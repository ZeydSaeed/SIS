<?php

namespace App\Application\Documents\Contracts;

interface DocumentObjectStoragePort
{
    public function put(string $storageKey, string $contents): void;

    public function get(string $storageKey): ?string;

    public function exists(string $storageKey): bool;
}
