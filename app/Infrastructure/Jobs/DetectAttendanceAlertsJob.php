<?php

namespace App\Infrastructure\Jobs;

use App\Application\Attendance\Commands\DetectAttendanceAlertsCommand;
use App\Application\Attendance\Commands\DetectAttendanceAlertsHandler;
use App\Application\Attendance\Support\AttendancePolicyResolver;
use App\Security\Context\SchoolContextScope;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Background alert detection (never inside an HTTP request): for the current academic year, each active school —
 * or only $schoolId when dispatched from the alerts screen — evaluated under its own tenant context on its local
 * date. Re-runs the same hour are idempotent; alerts are deduplicated per rule · subject · day.
 */
final class DetectAttendanceAlertsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly ?int $schoolId = null,
    ) {}

    public function handle(DetectAttendanceAlertsHandler $handler, AttendancePolicyResolver $policies, SchoolContextScope $scope): void
    {
        $yearId = DB::table('academic.academic_years')->where('is_current', true)->value('id');
        if ($yearId === null) {
            return;
        }
        $schools = DB::table('organization.schools')->where('status', 1)
            ->when($this->schoolId !== null, fn ($q) => $q->where('id', $this->schoolId))
            ->pluck('id');

        foreach ($schools as $schoolId) {
            try {
                $scope->run((int) $schoolId, function () use ($handler, $policies, $schoolId, $yearId): void {
                    $policy = $policies->current((int) $schoolId, (int) $yearId);
                    $now = now()->toDateTimeImmutable();
                    $date = $policy->localDate($now);
                    $handler->handle(new DetectAttendanceAlertsCommand((int) $schoolId, (int) $yearId, $date, "att-alerts:{$schoolId}:{$date}:".$now->format('H')));
                });
            } catch (\Throwable $e) {
                Log::error('attendance.alerts.detect_failed', ['school_id' => $schoolId, 'error' => $e->getMessage()]);
            }
        }
    }
}
