<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Timetable\Results\RestoreTimetableVersionResult;
use App\Application\Timetable\Support\EngineTransaction;
use App\Domain\Timetable\Repositories\ScheduleRepositoryInterface;
use App\Domain\Timetable\Repositories\TimetableVersionRepositoryInterface;
use App\Domain\Timetable\Repositories\TimetableWorkspaceReadRepositoryInterface;

final class RestoreTimetableVersionHandler implements CommandHandler
{
    public function __construct(
        private readonly EngineTransaction $tx,
        private readonly TimetableVersionRepositoryInterface $versions,
        private readonly ScheduleRepositoryInterface $schedules,
        private readonly TimetableWorkspaceReadRepositoryInterface $workspace,
    ) {}

    public function handle(Command $command): RestoreTimetableVersionResult
    {
        assert($command instanceof RestoreTimetableVersionCommand);

        return $this->tx->run(RestoreTimetableVersionResult::class, $command->idempotencyKey, 'RestoreTimetableVersion', function () use ($command): RestoreTimetableVersionResult {
            $version = $this->versions->find($command->schoolId, $command->versionId);
            if ($version === null) {
                return RestoreTimetableVersionResult::failure('timetable.version_not_found');
            }
            $live = $this->workspace->activeSchedules($command->schoolId, $version['academic_year_id']);
            $cancel = array_column(array_filter($live, static fn (array $s): bool => ! ($s['locked'] ?? false)), 'id');
            $rows = self::rows($this->versions->entries($command->schoolId, $command->versionId));
            $written = $this->schedules->replaceGrid($command->schoolId, $version['academic_year_id'], $cancel, $rows, (new \DateTimeImmutable)->format('Y-m-d H:i:sP'), $command->userId, $command->correlationId);
            $this->tx->stage('version_restored', $command->schoolId, $version['academic_year_id'], $command->versionId, $command->userId, ['rows' => $written]);

            return RestoreTimetableVersionResult::success($command->versionId, ['rows' => $written]);
        });
    }

    /**
     * Version entries back to grid rows; entries of one teacher in one slot are one joined lesson (first section leads).
     *
     * @return list<array<string, mixed>>
     */
    private static function rows(array $entries): array
    {
        $rows = [];
        $leads = [];
        foreach ($entries as $e) {
            $key = $e['teacher_id'].':'.$e['day_of_week'].':'.$e['period_id'].':'.($e['week_no'] ?? 0);
            $isLead = ! isset($leads[$key]);
            $leads[$key] = true;
            $rows[] = ['lead_key' => $key, 'is_lead' => $isLead] + array_intersect_key($e, array_flip(['section_id', 'group_id', 'day_of_week', 'period_id', 'week_no', 'subject_id', 'teacher_id', 'co_teacher_id', 'room_id', 'activity_id']));
        }

        return $rows;
    }
}
