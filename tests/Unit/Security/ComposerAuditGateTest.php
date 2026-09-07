<?php

namespace Tests\Unit\Security;

use App\Security\Validation\ComposerAuditGate;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ComposerAuditGateTest extends TestCase
{
    #[Test]
    public function clean_audit_fixture_passes_policy(): void
    {
        $fixture = (string) file_get_contents(base_path('tests/fixtures/security/composer-audit-clean.json'));

        $result = (new ComposerAuditGate)->evaluate($fixture, 0);

        $this->assertTrue($result['passed']);
        $this->assertSame(0, $result['blocking_count']);
    }

    #[Test]
    public function blocking_audit_fixture_fails_policy(): void
    {
        $fixture = (string) file_get_contents(base_path('tests/fixtures/security/composer-audit-blocking.json'));

        $result = (new ComposerAuditGate)->evaluate($fixture, 1);

        $this->assertFalse($result['passed']);
        $this->assertGreaterThan(0, $result['blocking_count']);
    }
}
