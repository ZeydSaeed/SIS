<?php

namespace Tests\Unit\Console;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The runbook's disposable-copy procedure (§4.1) must keep using the guarded tool, never a raw CREATE / DROP, and must
 * describe the fixture guard accurately.
 */
final class E2eRunbookDisposableSectionTest extends TestCase
{
    private function section(): string
    {
        $runbook = (string) file_get_contents(dirname(__DIR__, 3).'/docs/testing/E2E-RUNBOOK.md');
        $start = strpos($runbook, '### 4.1');
        $end = strpos($runbook, '## 5.', (int) $start);
        $this->assertNotFalse($start, 'runbook §4.1 is missing');
        $this->assertNotFalse($end);

        return substr($runbook, (int) $start, (int) $end - (int) $start);
    }

    #[Test]
    public function the_copy_is_created_and_dropped_only_through_the_guarded_tool(): void
    {
        $section = $this->section();

        $this->assertStringContainsString('artisan sis:e2e-disposable create sis_e2e_exams', $section);
        $this->assertStringContainsString('artisan sis:e2e-disposable drop sis_e2e_exams', $section);
        $this->assertStringContainsString('--execute', $section);
        $this->assertDoesNotMatchRegularExpression('/^\s*psql\b.*\b(CREATE|DROP)\s+DATABASE/im', $section);
    }

    #[Test]
    public function the_fixture_guard_is_described_accurately(): void
    {
        $section = $this->section();

        $this->assertStringNotContainsString('before its first query', $section);
        $this->assertStringContainsString('configuration only', $section);
        $this->assertStringContainsString('read-only', $section);
    }
}
