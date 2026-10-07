<?php

namespace App\Infrastructure\Persistence\Timetable;

use App\Database\SchemaHelper;
use App\Domain\Timetable\Repositories\TimetableVersionRepositoryInterface;
use App\Domain\Timetable\ValueObjects\ScheduleLifecycleStatus;
use App\Domain\Timetable\ValueObjects\TimetableVersionStatus;
use Illuminate\Support\Facades\DB;

final class EloquentTimetableVersionRepository implements TimetableVersionRepositoryInterface
{
    private const COLUMNS = ['id', 'academic_year_id', 'version_no', 'parent_version_id', 'name', 'reason', 'status', 'source_fingerprint',
        'entries_count', 'quality', 'generation_run_id', 'approval_request_id', 'created_by', 'decided_by', 'decided_at',
        'published_by', 'published_at', 'effective_from', 'effective_to', 'created_at'];

    public function snapshotWorkingGrid(int $schoolId, int $academicYearId, string $name, ?string $reason, string $fingerprint, array $quality, ?int $parentVersionId, ?int $generationRunId, ?int $userId): int
    {
        $this->bindSchool($schoolId);
        // Serialise numbering per school-year (the unique key backs it up).
        DB::statement('SELECT pg_advisory_xact_lock(?, ?)', [7310, $schoolId * 1000 + ($academicYearId % 1000)]);
        $next = (int) DB::table($this->table())->where('school_id', $schoolId)->where('academic_year_id', $academicYearId)->max('version_no') + 1;
        $id = (int) DB::table($this->table())->insertGetId([
            'school_id' => $schoolId,
            'academic_year_id' => $academicYearId,
            'version_no' => $next,
            'parent_version_id' => $parentVersionId,
            'name' => $name,
            'reason' => $reason,
            'status' => TimetableVersionStatus::Draft->value,
            'source_fingerprint' => $fingerprint,
            'quality' => json_encode($quality, JSON_THROW_ON_ERROR),
            'generation_run_id' => $generationRunId,
            'created_by' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $entries = SchemaHelper::qualified('timetable', 'version_entries');
        $schedules = SchemaHelper::qualified('timetable', 'schedules');
        $count = DB::affectingStatement("
            INSERT INTO {$entries} (school_id, version_id, section_id, group_id, day_of_week, period_id, week_no, subject_id,
                teacher_id, co_teacher_id, room_id, activity_id, source_schedule_id, created_at)
            SELECT school_id, ?, section_id, group_id, day_of_week, period_id, week_no, subject_id,
                teacher_id, co_teacher_id, room_id, activity_id, id, NOW()
            FROM {$schedules}
            WHERE school_id = ? AND academic_year_id = ? AND lifecycle_status = ? AND cancelled_at IS NULL
            ORDER BY section_id, day_of_week, period_id, id
        ", [$id, $schoolId, $academicYearId, ScheduleLifecycleStatus::Active->value]);
        DB::table($this->table())->where('id', $id)->update(['entries_count' => $count]);

        return $id;
    }

    public function find(int $schoolId, int $versionId): ?array
    {
        $this->bindSchool($schoolId);
        $row = DB::table($this->table())->where('school_id', $schoolId)->where('id', $versionId)->first(self::COLUMNS);

        return $row === null ? null : $this->map($row);
    }

    public function listForYear(int $schoolId, int $academicYearId): array
    {
        $this->bindSchool($schoolId);

        return DB::table($this->table())->where('school_id', $schoolId)->where('academic_year_id', $academicYearId)
            ->orderByDesc('version_no')->get(self::COLUMNS)->map(fn (object $r): array => $this->map($r))->all();
    }

    public function entries(int $schoolId, int $versionId, ?int $sectionId = null, ?int $teacherId = null): array
    {
        $this->bindSchool($schoolId);
        $query = DB::table(SchemaHelper::qualified('timetable', 'version_entries'))->where('school_id', $schoolId)->where('version_id', $versionId);
        if ($sectionId !== null) {
            $query->where('section_id', $sectionId);
        }
        if ($teacherId !== null) {
            $query->where(static fn ($q) => $q->where('teacher_id', $teacherId)->orWhere('co_teacher_id', $teacherId));
        }

        return $query->orderBy('section_id')->orderBy('day_of_week')->orderBy('period_id')->orderBy('id')
            ->get(['id', 'section_id', 'group_id', 'day_of_week', 'period_id', 'week_no', 'subject_id', 'teacher_id', 'co_teacher_id', 'room_id', 'activity_id', 'source_schedule_id'])
            ->map(static fn (object $r): array => [
                'id' => (int) $r->id,
                'section_id' => (int) $r->section_id,
                'group_id' => $r->group_id !== null ? (int) $r->group_id : null,
                'day_of_week' => (int) $r->day_of_week,
                'period_id' => (int) $r->period_id,
                'week_no' => $r->week_no !== null ? (int) $r->week_no : null,
                'subject_id' => (int) $r->subject_id,
                'teacher_id' => (int) $r->teacher_id,
                'co_teacher_id' => $r->co_teacher_id !== null ? (int) $r->co_teacher_id : null,
                'room_id' => $r->room_id !== null ? (int) $r->room_id : null,
                'activity_id' => $r->activity_id !== null ? (int) $r->activity_id : null,
                'source_schedule_id' => $r->source_schedule_id !== null ? (int) $r->source_schedule_id : null,
            ])->all();
    }

    public function setStatus(int $schoolId, int $versionId, int $from, int $to, array $fields = []): bool
    {
        $this->bindSchool($schoolId);

        return DB::table($this->table())->where('school_id', $schoolId)->where('id', $versionId)->where('status', $from)
            ->update(['status' => $to, 'updated_at' => now()] + $fields) > 0;
    }

    public function currentPublished(int $schoolId, int $academicYearId): ?array
    {
        $this->bindSchool($schoolId);
        $row = DB::table($this->table())->where('school_id', $schoolId)->where('academic_year_id', $academicYearId)
            ->where('status', TimetableVersionStatus::Published->value)
            ->orderByDesc('effective_from')->orderByDesc('id')->first(self::COLUMNS);

        return $row === null ? null : $this->map($row);
    }

    public function supersedePublished(int $schoolId, int $academicYearId, int $exceptVersionId, string $effectiveFrom): int
    {
        $this->bindSchool($schoolId);
        $end = (new \DateTimeImmutable($effectiveFrom))->modify('-1 day')->format('Y-m-d');

        return DB::table($this->table())->where('school_id', $schoolId)->where('academic_year_id', $academicYearId)
            ->where('status', TimetableVersionStatus::Published->value)->where('id', '!=', $exceptVersionId)
            ->update([
                'status' => TimetableVersionStatus::Superseded->value,
                'effective_to' => DB::raw("CASE WHEN effective_to IS NULL OR effective_to > DATE '{$end}' THEN GREATEST(effective_from, DATE '{$end}') ELSE effective_to END"),
                'updated_at' => now(),
            ]);
    }

    public function findByApprovalRequest(int $schoolId, int $approvalRequestId): ?array
    {
        $this->bindSchool($schoolId);
        $row = DB::table($this->table())->where('school_id', $schoolId)->where('approval_request_id', $approvalRequestId)->first(self::COLUMNS);

        return $row === null ? null : $this->map($row);
    }

    private function map(object $r): array
    {
        return [
            'id' => (int) $r->id,
            'academic_year_id' => (int) $r->academic_year_id,
            'version_no' => (int) $r->version_no,
            'parent_version_id' => $r->parent_version_id !== null ? (int) $r->parent_version_id : null,
            'name' => (string) $r->name,
            'reason' => $r->reason,
            'status' => (int) $r->status,
            'source_fingerprint' => (string) $r->source_fingerprint,
            'entries_count' => (int) $r->entries_count,
            'quality' => $r->quality !== null ? (array) json_decode((string) $r->quality, true) : null,
            'generation_run_id' => $r->generation_run_id !== null ? (int) $r->generation_run_id : null,
            'approval_request_id' => $r->approval_request_id !== null ? (int) $r->approval_request_id : null,
            'created_by' => $r->created_by !== null ? (int) $r->created_by : null,
            'decided_by' => $r->decided_by !== null ? (int) $r->decided_by : null,
            'decided_at' => $r->decided_at,
            'published_by' => $r->published_by !== null ? (int) $r->published_by : null,
            'published_at' => $r->published_at,
            'effective_from' => $r->effective_from !== null ? substr((string) $r->effective_from, 0, 10) : null,
            'effective_to' => $r->effective_to !== null ? substr((string) $r->effective_to, 0, 10) : null,
            'created_at' => (string) $r->created_at,
        ];
    }

    private function table(): string
    {
        return SchemaHelper::qualified('timetable', 'versions');
    }

    private function bindSchool(int $schoolId): void
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
    }
}
