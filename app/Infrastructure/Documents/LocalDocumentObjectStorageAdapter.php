<?php

namespace App\Infrastructure\Documents;

use App\Application\Documents\Contracts\DocumentObjectStoragePort;
use Illuminate\Support\Facades\Storage;

final class LocalDocumentObjectStorageAdapter implements DocumentObjectStoragePort
{
    public function put(string $storageKey, string $contents): void
    {
        Storage::disk($this->disk())->put($storageKey, $contents);
    }

    public function get(string $storageKey): ?string
    {
        if (! $this->exists($storageKey)) {
            return null;
        }

        $contents = Storage::disk($this->disk())->get($storageKey);

        return is_string($contents) ? $contents : null;
    }

    public function exists(string $storageKey): bool
    {
        return Storage::disk($this->disk())->exists($storageKey);
    }

    private function disk(): string
    {
        return (string) config('sis.documents.disk', 'sis_documents');
    }
}
