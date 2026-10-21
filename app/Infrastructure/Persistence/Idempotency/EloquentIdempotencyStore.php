<?php

namespace App\Infrastructure\Persistence\Idempotency;

use App\Application\Contracts\IdempotencyStore;
use App\Database\SchemaHelper;
use Illuminate\Support\Facades\DB;

/**
 * audit.idempotency_keys.key is VARCHAR(64), but keys arrive from clients (X-Idempotency-Key, any length) and
 * handlers append their own suffixes (`:convert:<id>`, `:document`, …). A longer key is stored as a fixed-width
 * digest of itself — the same key always maps to the same row, so a retry still finds its result — instead of
 * failing after the command's write has already been committed.
 */
final class EloquentIdempotencyStore implements IdempotencyStore
{
    private const KEY_WIDTH = 64;

    public function find(string $key, string $commandName): ?array
    {
        $row = DB::table(SchemaHelper::qualified('audit', 'idempotency_keys'))
            ->where('key', self::column($key))
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
            ['key' => self::column($key), 'command_name' => $commandName],
            [
                'response_payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'expires_at' => now()->addSeconds($ttlSeconds),
            ],
        );
    }

    public static function column(string $key): string
    {
        return mb_strlen($key) <= self::KEY_WIDTH ? $key : 'h:'.substr(hash('sha256', $key), 0, self::KEY_WIDTH - 2);
    }
}
