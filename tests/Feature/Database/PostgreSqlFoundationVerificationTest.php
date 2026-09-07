<?php

namespace Tests\Feature\Database;

use App\Database\DatabaseFoundationVerifier;
use App\Database\SchemaHelper;
use Database\Seeders\SisFoundationSeeder;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PostgreSqlFoundationVerificationTest extends TestCase
{
    #[Test]
    public function postgresql_foundation_verification_passes_after_seed(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            $this->markTestSkipped('PostgreSQL required for foundation verification.');
        }

        $this->seed(SisFoundationSeeder::class);

        $report = app(DatabaseFoundationVerifier::class)->verify();

        $this->assertTrue($report['ok'], json_encode($report['checks'], JSON_THROW_ON_ERROR));
        $this->assertSame('pgsql', $report['driver']);
        $this->assertGreaterThanOrEqual(50, $report['summary']['table_count']);
        $this->assertTrue($report['summary']['foundation_seed_present']);
    }

    #[Test]
    public function verify_database_artisan_command_succeeds_on_postgresql(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            $this->markTestSkipped('PostgreSQL required for foundation verification.');
        }

        $this->seed(SisFoundationSeeder::class);

        $this->artisan('sis:verify-database')
            ->assertSuccessful();
    }
}
