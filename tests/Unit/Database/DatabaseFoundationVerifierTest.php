<?php

namespace Tests\Unit\Database;

use App\Database\DatabaseFoundationVerifier;
use Database\Seeders\SisFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DatabaseFoundationVerifierTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function verifier_fails_when_foundation_seed_missing(): void
    {
        $report = app(DatabaseFoundationVerifier::class)->verify();

        $failed = collect($report['checks'])
            ->firstWhere('name', 'foundation_seed');

        $this->assertNotNull($failed);
        $this->assertSame('fail', $failed['status']);
        $this->assertFalse($report['ok']);
    }

    #[Test]
    public function verifier_passes_after_foundation_seed_on_sqlite(): void
    {
        $this->seed(SisFoundationSeeder::class);

        $report = app(DatabaseFoundationVerifier::class)->verify();

        $this->assertTrue($report['summary']['foundation_seed_present']);
        $this->assertTrue(
            collect($report['checks'])->contains(
                fn (array $check): bool => $check['name'] === 'foundation_seed' && $check['status'] === 'pass',
            ),
        );

        $schoolCheck = collect($report['checks'])->firstWhere('name', 'table:organization.schools');
        $this->assertSame('pass', $schoolCheck['status'] ?? null);
    }

    #[Test]
    public function verifier_can_skip_foundation_seed_check(): void
    {
        $report = app(DatabaseFoundationVerifier::class)->verify(requireFoundationSeed: false);

        $this->assertNull(
            collect($report['checks'])->firstWhere('name', 'foundation_seed'),
        );
    }
}
