<?php

namespace App\Infrastructure\Persistence\Idempotency;

use App\Application\Contracts\IdempotencyStore;
use App\Database\SchemaHelper;
use Illuminate\Support\Facades\DB;

final class EloquentIdempotencyStore implements IdempotencyStore
{
    public function find(string $key, string $commandName): ?array
    {
        $row = DB::table(SchemaHelper::qualified('audit', 'idempotency_keys'))
            ->where('key', $key)
            ->where('command_name', $commandName)
            ->where('expires_at', '>', now())
            ->first();

        if ($row === null) {
            return null;
        }

        /** @var array<string, mixed> */
        return json_decode((string) $row->response_payload, true, 512, JSON_THROW_ON_ERROR);
    }

    public function store(string $key, string $commandName, array $payload, int $ttlSeconds = 86400): void
    {
        DB::table(SchemaHelper::qualified('audit', 'idempotency_keys'))->updateOrInsert(
            ['key' => $key, 'command_name' => $commandName],
            [
                'response_payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'expires_at' => now()->addSeconds($ttlSeconds),
            ],
        );
    }
}
