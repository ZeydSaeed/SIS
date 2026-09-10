<?php

namespace Tests\Unit\Database;

use App\Database\Exceptions\ProtectedDatabaseException;
use App\Database\ProtectedDatabaseGuard;
use App\Database\SchemaHelper;
use Tests\TestCase;

/**
 * Regression: destructive paths must never treat protected "sis" as disposable.
 * Does NOT connect to or wipe the live sis database.
 */
class ProtectedDatabaseRegressionTest extends TestCase
{
    public function test_guard_blocks_testing_environment_when_target_is_sis(): void
    {
        $this->assertSame('testing', app()->environment());

        $guard = $this->app->make(ProtectedDatabaseGuard::class);

        $this->assertTrue($guard->isProtected('sis'));
        $this->assertFalse($guard->allowsDestructiveOperations('sis'));

        $this->expectException(ProtectedDatabaseException::class);
        $guard->assertSafeForDestructiveOperations('sis');
    }

    public function test_schema_helper_drop_schemas_is_blocked_when_connection_is_protected(): void
    {
        config([
            'sis.database.force_protected' => true,
            'sis.database.protected_names' => ['sis'],
            'sis.database.destructive_allowed_names' => ['sis_test', ':memory:'],
        ]);

        $this->expectException(ProtectedDatabaseException::class);
        SchemaHelper::dropSchemas();
    }
}
