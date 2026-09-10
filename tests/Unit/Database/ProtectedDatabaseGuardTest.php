<?php

namespace Tests\Unit\Database;

use App\Database\Exceptions\ProtectedDatabaseException;
use App\Database\ProtectedDatabaseGuard;
use Tests\TestCase;

class ProtectedDatabaseGuardTest extends TestCase
{
    public function test_sis_is_protected_and_blocks_destructive_ops(): void
    {
        config([
            'sis.database.protected_names' => ['sis'],
            'sis.database.destructive_allowed_names' => ['sis_test', ':memory:'],
            'sis.database.force_protected' => false,
        ]);

        $guard = $this->app->make(ProtectedDatabaseGuard::class);

        $this->assertTrue($guard->isProtected('sis'));
        $this->assertFalse($guard->allowsDestructiveOperations('sis'));

        $this->expectException(ProtectedDatabaseException::class);
        $guard->assertSafeForDestructiveOperations('sis');
    }

    public function test_sis_test_and_memory_are_allowed_for_destructive_ops_when_not_protected(): void
    {
        config([
            'sis.database.protected_names' => ['sis'],
            'sis.database.destructive_allowed_names' => ['sis_test', ':memory:'],
            'sis.database.force_protected' => false,
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        $guard = $this->app->make(ProtectedDatabaseGuard::class);

        $this->assertFalse($guard->isProtected('sis_test'));
        $this->assertFalse($guard->isProtected(':memory:'));
        $this->assertTrue($guard->allowsDestructiveOperations(':memory:'));
    }

    public function test_force_protected_flag_blocks_any_target(): void
    {
        config([
            'sis.database.protected_names' => ['sis'],
            'sis.database.destructive_allowed_names' => ['sis_test', ':memory:'],
            'sis.database.force_protected' => true,
        ]);

        $guard = $this->app->make(ProtectedDatabaseGuard::class);

        $this->assertTrue($guard->isProtected('sis_test'));
        $this->expectException(ProtectedDatabaseException::class);
        $guard->assertSafeForDestructiveOperations('sis_test');
    }

    public function test_unknown_database_name_is_fail_closed_for_destructive_ops(): void
    {
        config([
            'sis.database.protected_names' => ['sis'],
            'sis.database.destructive_allowed_names' => ['sis_test', ':memory:'],
            'sis.database.force_protected' => false,
        ]);

        $guard = $this->app->make(ProtectedDatabaseGuard::class);

        $this->assertFalse($guard->isProtected('random_db'));
        $this->assertFalse($guard->allowsDestructiveOperations('random_db'));
        $this->expectException(ProtectedDatabaseException::class);
        $guard->assertSafeForDestructiveOperations('random_db');
    }
}
