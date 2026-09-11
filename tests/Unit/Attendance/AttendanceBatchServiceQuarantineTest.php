<?php

namespace Tests\Unit\Attendance;

use App\Domain\Attendance\Exceptions\LegacyAttendanceWriterQuarantinedException;
use App\Services\Attendance\AttendanceBatchService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class AttendanceBatchServiceQuarantineTest extends TestCase
{
    #[Test]
    public function record_section_attendance_is_blocked(): void
    {
        $service = new AttendanceBatchService;

        try {
            $service->recordSectionAttendance(1, 2, 3, 4, [
                ['student_id' => 1, 'enrollment_id' => 1, 'status' => 1],
            ], 9);
            $this->fail('Expected LegacyAttendanceWriterQuarantinedException');
        } catch (LegacyAttendanceWriterQuarantinedException $e) {
            $this->assertSame('attendance.legacy_writer_quarantined', $e->errorCode());
            $this->assertStringNotContainsString('SQLSTATE', $e->getMessage());
            $this->assertStringNotContainsString('23505', $e->getMessage());
        }
    }

    #[Test]
    public function refresh_daily_summary_is_blocked(): void
    {
        $service = new AttendanceBatchService;

        $this->expectException(LegacyAttendanceWriterQuarantinedException::class);
        $service->refreshDailySummary(1, 2, 3, '2026-10-01');
    }

    #[Test]
    public function attendance_controller_does_not_typehint_legacy_writer(): void
    {
        $controller = new \ReflectionClass(\App\Http\Controllers\Api\AttendanceController::class);

        foreach ($controller->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== $controller->getName()) {
                continue;
            }

            foreach ($method->getParameters() as $parameter) {
                $type = $parameter->getType();
                if ($type instanceof \ReflectionNamedType) {
                    $this->assertNotSame(
                        AttendanceBatchService::class,
                        $type->getName(),
                        "HTTP method {$method->getName()} must not resolve the quarantined legacy writer",
                    );
                }
            }
        }

        $source = (string) file_get_contents($controller->getFileName());
        $this->assertStringNotContainsString('AttendanceBatchService', $source);
        $this->assertStringNotContainsString('App\\Services\\Attendance\\AttendanceBatchService', $source);
    }
}
