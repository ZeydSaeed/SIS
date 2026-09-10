<?php

namespace Tests;

use App\Database\ProtectedDatabaseGuard;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * Fail-closed: RefreshDatabase must never target protected databases (e.g. sis).
     *
     * @return void
     */
    protected function beforeRefreshingDatabase()
    {
        $this->app->make(ProtectedDatabaseGuard::class)->assertSafeForDestructiveOperations();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
