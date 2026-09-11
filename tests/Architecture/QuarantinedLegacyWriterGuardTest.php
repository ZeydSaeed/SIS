<?php

namespace Tests\Architecture;

use App\Architecture\QuarantinedLegacyWriterGuard;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class QuarantinedLegacyWriterGuardTest extends TestCase
{
    #[Test]
    public function live_codebase_has_no_unsupported_legacy_attendance_writer_references(): void
    {
        $guard = new QuarantinedLegacyWriterGuard;

        $this->assertSame([], $guard->validate(), implode("\n", $guard->validate()));
    }

    #[Test]
    public function unsupported_reference_is_detected_deterministically(): void
    {
        $tmp = base_path('app/Http/Controllers/_R19QuarantineProbeController.php');
        $payload = <<<'PHP'
<?php
namespace App\Http\Controllers;
use App\Services\Attendance\AttendanceBatchService;
class _R19QuarantineProbeController
{
    public function __construct(private AttendanceBatchService $legacy) {}
}
PHP;

        try {
            file_put_contents($tmp, $payload);
            $violations = (new QuarantinedLegacyWriterGuard)->validate();
            $this->assertNotSame([], $violations);
            $this->assertTrue(
                collect($violations)->contains(fn (string $v) => str_contains($v, '[ARCH-LEGACY-001]')),
                implode("\n", $violations),
            );
        } finally {
            if (is_file($tmp)) {
                unlink($tmp);
            }
        }
    }
}
