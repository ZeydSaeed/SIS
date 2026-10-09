<?php

namespace App\Infrastructure\Persistence\Timetable;

use App\Database\SchemaHelper;
use App\Domain\Timetable\Repositories\TimetableConfigurationRepositoryInterface;
use App\Domain\Timetable\Support\TimetableSettings;
use Illuminate\Support\Facades\DB;

final class EloquentTimetableConfigurationRepository implements TimetableConfigurationRepositoryInterface
{
    private const ACTIVE = 1;

    private const ENDED = 2;

    public function saveSettings(int $schoolId, int $academicYearId, TimetableSettings $settings, ?int $userId): void
    {
        $this->bindSchool($schoolId);
        $values = [
            'working_days' => json_encode(array_values($settings->days), JSON_THROW_ON_ERROR),
            'cycle_weeks' => $settings->cycleWeeks,
            'max_teacher_per_day' => $settings->maxTeacherPerDay,
            'max_subject_per_day' => $settings->maxSubjectPerDay,
            'double_changeover_minutes' => $settings->doubleChangeoverMinutes,
            'weights' => $settings->weights === [] ? null : json_encode($settings->weights, JSON_THROW_ON_ERROR),
            'updated_by' => $userId,
            'updated_at' => now(),
        ];
        $table = SchemaHelper::qualified('timetable', 'configs');
        $updated = DB::table($table)->where('school_id', $schoolId)->where('academic_year_id', $academicYearId)->update($values);
        if ($updated === 0) {
            DB::table($table)->insert($values + ['school_id' => $schoolId, 'academic_year_id' => $academicYearId, 'created_at' => now()]);
        }
    }

    public function saveDisplay(int $schoolId, int $academicYearId, array $display, ?int $userId): void
    {
        $this->bindSchool($schoolId);
        $table = SchemaHelper::qualified('timetable', 'configs');
        $values = ['display' => json_encode($display, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), 'updated_by' => $userId, 'updated_at' => now()];
        $updated = DB::table($table)->where('school_id', $schoolId)->where('academic_year_id', $academicYearId)->update($values);
        if ($updated === 0) {
            // A school-year without settings yet: the defaults row (DB defaults) carrying the display.
            DB::table($table)->insert($values + ['school_id' => $schoolId, 'academic_year_id' => $academicYearId, 'created_at' => now()]);
        }
    }

    public function insertActivity(int $schoolId, int $academicYearId, array $data, array $targets, array $teachers, ?int $userId): int
    {
        $this->bindSchool($schoolId);
        $id = (int) DB::table(SchemaHelper::qualified('timetable', 'activities'))->insertGetId([
            'school_id' => $schoolId,
            'academic_year_id' => $academicYearId,
            'subject_id' => $data['subject_id'],
            'activity_type' => $data['activity_type'],
            'weekly_count' => $data['weekly_count'],
            'block_length' => $data['block_length'],
            'distribution' => $data['distribution'],
            'distribution_fixed' => $data['distribution'] !== null,
            'room_id' => $data['room_id'],
            'room_type' => $data['room_type'],
            'workshop_id' => $data['workshop_id'],
            'week_pattern' => $data['week_pattern'],
            'term_id' => $data['term_id'],
            'note' => $data['note'],
            'created_by' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table(SchemaHelper::qualified('timetable', 'activity_sections'))->insert(array_map(static fn (array $t): array => [
            'school_id' => $schoolId, 'activity_id' => $id, 'section_id' => $t['section_id'], 'group_id' => $t['group_id'], 'created_at' => now(),
        ], $targets));
        DB::table(SchemaHelper::qualified('timetable', 'activity_teachers'))->insert(array_map(static fn (array $t): array => [
            'school_id' => $schoolId, 'activity_id' => $id, 'teacher_id' => $t['teacher_id'], 'role' => $t['role'], 'sessions' => $t['sessions'], 'created_at' => now(),
        ], $teachers));

        return $id;
    }

    public function updateActivity(int $schoolId, int $activityId, array $data): bool
    {
        $this->bindSchool($schoolId);

        return DB::table(SchemaHelper::qualified('timetable', 'activities'))
            ->where('school_id', $schoolId)->where('id', $activityId)->where('status', self::ACTIVE)
            ->update([
                'activity_type' => $data['activity_type'],
                'weekly_count' => $data['weekly_count'],
                'block_length' => $data['block_length'],
                'distribution' => $data['distribution'],
                'distribution_fixed' => $data['distribution'] !== null,
                'room_id' => $data['room_id'],
                'room_type' => $data['room_type'],
                'workshop_id' => $data['workshop_id'],
                'week_pattern' => $data['week_pattern'],
                'note' => $data['note'],
                'updated_at' => now(),
            ]) > 0;
    }

    public function endActivity(int $schoolId, int $activityId, string $onDate): bool
    {
        $this->bindSchool($schoolId);

        return DB::table(SchemaHelper::qualified('timetable', 'activities'))
            ->where('school_id', $schoolId)->where('id', $activityId)->where('status', self::ACTIVE)
            ->update(['status' => self::ENDED, 'effective_to' => DB::raw("GREATEST(effective_from, DATE '{$this->date($onDate)}')"), 'updated_at' => now()]) > 0;
    }

    public function findActivity(int $schoolId, int $activityId): ?array
    {
        $this->bindSchool($schoolId);
        $row = DB::table(SchemaHelper::qualified('timetable', 'activities'))
            ->where('school_id', $schoolId)->where('id', $activityId)
            ->first(['id', 'academic_year_id', 'status', 'subject_id']);

        return $row === null ? null : ['id' => (int) $row->id, 'academic_year_id' => (int) $row->academic_year_id, 'status' => (int) $row->status, 'subject_id' => (int) $row->subject_id];
    }

    public function insertDivision(int $schoolId, int $academicYearId, int $sectionId, string $name, array $groups): array
    {
        $this->bindSchool($schoolId);
        $divisionId = (int) DB::table(SchemaHelper::qualified('timetable', 'divisions'))->insertGetId([
            'school_id' => $schoolId, 'academic_year_id' => $academicYearId, 'section_id' => $sectionId,
            'name' => $name, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $ids = [];
        foreach ($groups as $group) {
            $groupId = (int) DB::table(SchemaHelper::qualified('timetable', 'division_groups'))->insertGetId([
                'school_id' => $schoolId, 'division_id' => $divisionId, 'name' => $group['name'],
                'student_count' => $group['student_count'], 'created_at' => now(), 'updated_at' => now(),
            ]);
            if ($group['members'] !== []) {
                DB::table(SchemaHelper::qualified('timetable', 'group_members'))->insert(array_map(static fn (int $enrollmentId): array => [
                    'school_id' => $schoolId, 'group_id' => $groupId, 'enrollment_id' => $enrollmentId, 'created_at' => now(), 'updated_at' => now(),
                ], $group['members']));
            }
            $ids[] = $groupId;
        }

        return $ids;
    }

    public function endDivision(int $schoolId, int $divisionId, string $onDate): bool
    {
        $this->bindSchool($schoolId);
        $ended = DB::table(SchemaHelper::qualified('timetable', 'divisions'))
            ->where('school_id', $schoolId)->where('id', $divisionId)->where('status', self::ACTIVE)
            ->update(['status' => self::ENDED, 'updated_at' => now()]) > 0;
        if (! $ended) {
            return false;
        }
        $groupIds = DB::table(SchemaHelper::qualified('timetable', 'division_groups'))->where('division_id', $divisionId)->pluck('id')->all();
        DB::table(SchemaHelper::qualified('timetable', 'division_groups'))->where('division_id', $divisionId)->update(['status' => self::ENDED, 'updated_at' => now()]);
        DB::table(SchemaHelper::qualified('timetable', 'group_members'))->whereIn('group_id', $groupIds)->where('status', self::ACTIVE)
            ->update(['status' => self::ENDED, 'effective_to' => DB::raw("GREATEST(effective_from, DATE '{$this->date($onDate)}')"), 'updated_at' => now()]);

        return true;
    }

    public function setAvailability(int $schoolId, int $academicYearId, array $target, array $slots, ?int $kind, ?int $weekNo, ?string $reason, ?int $userId): int
    {
        $this->bindSchool($schoolId);
        $table = SchemaHelper::qualified('timetable', 'availability');
        $columns = ['teacher_id', 'room_id', 'section_id', 'workshop_id'];
        $changed = 0;
        foreach ($slots as $slot) {
            $match = DB::table($table)->where('school_id', $schoolId)->where('academic_year_id', $academicYearId)
                ->where('day_of_week', $slot['day'])->where('period_id', $slot['period_id'])->where('status', self::ACTIVE);
            $weekNo === null ? $match->whereNull('week_no') : $match->where('week_no', $weekNo);
            foreach ($columns as $column) {
                ($target[$column] ?? null) === null ? $match->whereNull($column) : $match->where($column, $target[$column]);
            }
            $current = (clone $match)->value('kind');
            if ($current !== null && (int) $current === $kind) {
                continue;
            }
            if ($current !== null) {
                $match->update(['status' => self::ENDED, 'updated_at' => now()]);
                $changed++;
            }
            if ($kind !== null) {
                $row = ['school_id' => $schoolId, 'academic_year_id' => $academicYearId, 'day_of_week' => $slot['day'], 'period_id' => $slot['period_id'],
                    'week_no' => $weekNo, 'kind' => $kind, 'reason' => $reason, 'created_by' => $userId, 'created_at' => now(), 'updated_at' => now()];
                foreach ($columns as $column) {
                    $row[$column] = $target[$column] ?? null;
                }
                DB::table($table)->insert($row);
                $changed += $current === null ? 1 : 0;
            }
        }

        return $changed;
    }

    public function insertRule(int $schoolId, int $academicYearId, string $type, int $priority, array $scope, array $params, ?string $reason, ?int $userId): int
    {
        $this->bindSchool($schoolId);
        $row = [
            'school_id' => $schoolId, 'academic_year_id' => $academicYearId, 'rule_type' => $type, 'priority' => $priority,
            'params' => json_encode((object) $params, JSON_THROW_ON_ERROR), 'reason' => $reason, 'created_by' => $userId,
            'created_at' => now(), 'updated_at' => now(),
        ];
        foreach (['branch_id', 'department_id', 'grade_level_id', 'class_id', 'section_id', 'teacher_id', 'subject_id', 'room_id', 'activity_id', 'other_activity_id'] as $column) {
            $row[$column] = $scope[$column] ?? null;
        }

        return (int) DB::table(SchemaHelper::qualified('timetable', 'constraint_rules'))->insertGetId($row);
    }

    public function endRule(int $schoolId, int $ruleId, string $onDate): bool
    {
        $this->bindSchool($schoolId);

        return DB::table(SchemaHelper::qualified('timetable', 'constraint_rules'))
            ->where('school_id', $schoolId)->where('id', $ruleId)->where('status', self::ACTIVE)
            ->update(['status' => self::ENDED, 'effective_to' => DB::raw("GREATEST(effective_from, DATE '{$this->date($onDate)}')"), 'updated_at' => now()]) > 0;
    }

    public function referencesBelongToSchool(int $schoolId, int $academicYearId, array $refs): bool
    {
        $this->bindSchool($schoolId);
        $checks = [
            'section_ids' => fn (array $ids) => DB::table(SchemaHelper::qualified('enrollment', 'sections').' as s')
                ->join(SchemaHelper::qualified('enrollment', 'classes').' as c', 'c.id', '=', 's.class_id')
                ->whereIn('s.id', $ids)->where('c.school_id', $schoolId)->where('c.academic_year_id', $academicYearId)->count(),
            'teacher_ids' => fn (array $ids) => DB::table(SchemaHelper::qualified('teachers', 'teacher_schools'))
                ->whereIn('teacher_id', $ids)->where('school_id', $schoolId)->where('academic_year_id', $academicYearId)->distinct()->count('teacher_id'),
            'group_ids' => fn (array $ids) => DB::table(SchemaHelper::qualified('timetable', 'division_groups'))
                ->whereIn('id', $ids)->where('school_id', $schoolId)->where('status', self::ACTIVE)->count(),
            'room_ids' => fn (array $ids) => DB::table(SchemaHelper::qualified('organization', 'rooms').' as r')
                ->join(SchemaHelper::qualified('organization', 'branches').' as b', 'b.id', '=', 'r.branch_id')
                ->whereIn('r.id', $ids)->where('b.school_id', $schoolId)->count(),
            'workshop_ids' => fn (array $ids) => DB::table(SchemaHelper::qualified('vocational', 'workshops'))
                ->whereIn('id', $ids)->where('school_id', $schoolId)->count(),
            'activity_ids' => fn (array $ids) => DB::table(SchemaHelper::qualified('timetable', 'activities'))
                ->whereIn('id', $ids)->where('school_id', $schoolId)->where('academic_year_id', $academicYearId)->count(),
            'subject_ids' => fn (array $ids) => DB::table(SchemaHelper::qualified('curriculum', 'subjects'))->whereIn('id', $ids)->count(),
            'period_ids' => fn (array $ids) => DB::table(SchemaHelper::qualified('timetable', 'periods'))
                ->whereIn('id', $ids)->where('school_id', $schoolId)->where('period_type', 1)->count(),
            'branch_ids' => fn (array $ids) => DB::table(SchemaHelper::qualified('organization', 'branches'))->whereIn('id', $ids)->where('school_id', $schoolId)->count(),
            'department_ids' => fn (array $ids) => DB::table(SchemaHelper::qualified('organization', 'departments'))
                ->whereIn('id', $ids)->where('school_id', $schoolId)->count(),
            'class_ids' => fn (array $ids) => DB::table(SchemaHelper::qualified('enrollment', 'classes'))
                ->whereIn('id', $ids)->where('school_id', $schoolId)->where('academic_year_id', $academicYearId)->count(),
            'grade_level_ids' => fn (array $ids) => DB::table(SchemaHelper::qualified('academic', 'grade_levels'))->whereIn('id', $ids)->count(),
            'term_ids' => fn (array $ids) => DB::table(SchemaHelper::qualified('academic', 'terms'))->whereIn('id', $ids)->where('academic_year_id', $academicYearId)->count(),
        ];
        foreach ($refs as $kind => $ids) {
            $ids = array_values(array_unique(array_filter(array_map('intval', (array) $ids))));
            if ($ids === []) {
                continue;
            }
            if (! isset($checks[$kind]) || count($ids) !== $checks[$kind]($ids)) {
                return false;
            }
        }

        return true;
    }

    private function date(string $date): string
    {
        return (new \DateTimeImmutable($date))->format('Y-m-d');
    }

    private function bindSchool(int $schoolId): void
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
    }
}
