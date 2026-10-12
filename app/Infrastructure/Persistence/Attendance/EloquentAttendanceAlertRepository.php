<?php

namespace App\Infrastructure\Persistence\Attendance;

use App\Domain\Attendance\Data\AlertCandidate;
use App\Domain\Attendance\Repositories\AttendanceAlertRepositoryInterface;
use Illuminate\Support\Facades\DB;

final class EloquentAttendanceAlertRepository implements AttendanceAlertRepositoryInterface
{
    public function record(int $schoolId, int $academicYearId, string $periodStart, string $periodEnd, array $candidates): int
    {
        $created = 0;
        foreach (array_chunk($candidates, 200) as $chunk) {
            $created += DB::table('attendance.alerts')->insertOrIgnore(array_map(static fn (AlertCandidate $c): array => [
                'school_id' => $schoolId,
                'academic_year_id' => $academicYearId,
                'rule_code' => $c->ruleCode,
                'severity' => $c->severity,
                'subject_type' => $c->subjectType,
                'subject_id' => $c->subjectId,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'metric' => $c->metric,
                'threshold' => $c->threshold,
                'details' => $c->details === [] ? null : json_encode($c->details),
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], $chunk));
        }

        return $created;
    }

    public function setStatus(int $schoolId, int $alertId, int $status, ?int $userId, ?string $note): bool
    {
        return DB::table('attendance.alerts')
            ->where('id', $alertId)
            ->where('school_id', $schoolId)
            ->whereIn('status', [1, 2])
            ->update([
                'status' => $status,
                'resolved_by' => $userId,
                'resolved_at' => now(),
                'resolution_note' => $note,
                'updated_at' => now(),
            ]) === 1;
    }
}
