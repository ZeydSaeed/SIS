<?php

namespace Tests\Unit\Config;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SisEnvironmentProfileTest extends TestCase
{
    #[Test]
    public function testing_profile_disables_schedulers_by_default(): void
    {
        config(['sis.environment_profile' => 'testing']);

        $profile = config('sis.profiles.testing');

        $this->assertFalse($profile['intelligence_scheduler']);
        $this->assertFalse($profile['optimization_scheduler']);
        $this->assertSame('sqlite', $profile['database_connection']);
    }

    #[Test]
    public function local_profile_targets_postgresql_and_redis(): void
    {
        $profile = config('sis.profiles.local');

        $this->assertSame('pgsql', $profile['database_connection']);
        $this->assertSame('redis', $profile['cache_store']);
        $this->assertSame('redis', $profile['queue_connection']);
        $this->assertSame('observe', $profile['optimization_mode']);
    }

    #[Test]
    public function production_profile_never_lists_autonomous_mode(): void
    {
        $profile = config('sis.profiles.production');

        $this->assertSame('observe', $profile['optimization_mode']);
        $this->assertSame('pgsql', $profile['database_connection']);
    }
}
