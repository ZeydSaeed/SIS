<?php

namespace App\Database;

use App\Database\Exceptions\ProtectedDatabaseException;
use Illuminate\Support\Facades\DB;

/**
 * Fail-closed guard: protected databases (e.g. sis) must never be wiped/refreshed by tests or artisan.
 */
final class ProtectedDatabaseGuard
{
    public function normalizeName(?string $database): string
    {
        return strtolower(trim((string) $database));
    }

    /**
     * @return list<string>
     */
    public function protectedNames(): array
    {
        /** @var list<string>|string $configured */
        $configured = config('sis.database.protected_names', ['sis']);

        if (is_string($configured)) {
            $configured = array_filter(array_map('trim', explode(',', $configured)));
        }

        return array_values(array_map(fn (string $name): string => $this->normalizeName($name), $configured));
    }

    /**
     * @return list<string>
     */
    public function destructiveAllowedNames(): array
    {
        /** @var list<string>|string $configured */
        $configured = config('sis.database.destructive_allowed_names', ['sis_test', ':memory:']);

        if (is_string($configured)) {
            $configured = array_filter(array_map('trim', explode(',', $configured)));
        }

        return array_values(array_map(fn (string $name): string => $this->normalizeName($name), $configured));
    }

    public function resolveDatabaseName(?string $connection = null): string
    {
        return $this->normalizeName(DB::connection($connection)->getDatabaseName());
    }

    public function isProtected(?string $database = null, ?string $connection = null): bool
    {
        if (filter_var(config('sis.database.force_protected', false), FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }

        $name = $this->normalizeName($database ?? $this->resolveDatabaseName($connection));

        if ($name === '') {
            return true;
        }

        return in_array($name, $this->protectedNames(), true);
    }

    public function allowsDestructiveOperations(?string $database = null, ?string $connection = null): bool
    {
        $name = $this->normalizeName($database ?? $this->resolveDatabaseName($connection));

        if ($this->isProtected($name, $connection)) {
            return false;
        }

        $driver = DB::connection($connection)->getDriverName();

        if ($driver === 'sqlite') {
            return in_array($name, $this->destructiveAllowedNames(), true) || str_ends_with($name, '.sqlite');
        }

        if ($driver === 'pgsql') {
            return in_array($name, $this->destructiveAllowedNames(), true);
        }

        return false;
    }

    public function assertSafeForDestructiveOperations(?string $database = null, ?string $connection = null): void
    {
        $name = $this->normalizeName($database ?? $this->resolveDatabaseName($connection));

        if ($this->isProtected($name, $connection)) {
            throw ProtectedDatabaseException::destructiveBlocked(
                $name,
                'database is listed in sis.database.protected_names (fail-closed).'
            );
        }

        if (! $this->allowsDestructiveOperations($name, $connection)) {
            throw ProtectedDatabaseException::destructiveBlocked(
                $name,
                'database is not in sis.database.destructive_allowed_names (fail-closed).'
            );
        }
    }
}
