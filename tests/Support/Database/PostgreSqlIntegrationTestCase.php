<?php

namespace Tests\Support\Database;

use App\Database\ProtectedDatabaseGuard;
use App\Database\SchemaHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Base for PostgreSQL integration tests against disposable sis_test only.
 */
abstract class PostgreSqlIntegrationTestCase extends TestCase
{
    use RefreshDatabase {
        refreshTestDatabase as protected traitRefreshTestDatabase;
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (! SchemaHelper::isPostgreSql()) {
            $this->markTestSkipped('PostgreSQL required.');
        }

        $guard = $this->app->make(ProtectedDatabaseGuard::class);
        $database = $guard->resolveDatabaseName();

        if ($guard->isProtected($database)) {
            $this->fail("Refusing PostgreSQL integration tests against protected database [{$database}].");
        }

        $expected = (string) config('sis.database.pgsql_test_database', 'sis_test');
        if ($database !== strtolower($expected)) {
            $this->markTestSkipped(
                "PostgreSQL integration tests require DB_DATABASE={$expected} (got [{$database}]). Use phpunit.database-pgsql.xml."
            );
        }

        $this->resetSecuritySequences();
    }

    /**
     * security.roles / security.permissions have SMALLINT ids and every test re-seeds them inside its rolled-back
     * transaction — sequences are not transactional, so without this a full run exhausts them (max 32767).
     */
    private function resetSecuritySequences(): void
    {
        foreach (['security.roles', 'security.permissions'] as $table) {
            DB::statement(
                "SELECT setval(pg_get_serial_sequence('{$table}', 'id'), COALESCE((SELECT MAX(id) FROM {$table}), 0) + 1, false)"
            );
        }
    }

    /**
     * Multi-schema: drop SIS schemas before migrate:fresh so orphan schema tables cannot collide.
     */
    protected function refreshTestDatabase()
    {
        if (! RefreshDatabaseState::$migrated) {
            $this->beforeRefreshingDatabase();

            SchemaHelper::dropSchemas();

            $this->artisan('migrate:fresh', $this->migrateFreshUsing());

            RefreshDatabaseState::$migrated = true;

            $this->afterRefreshingDatabase();
        }

        $this->beginDatabaseTransaction();
    }
}
