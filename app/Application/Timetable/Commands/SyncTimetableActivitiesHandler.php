<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Timetable\Results\SyncTimetableActivitiesResult;
use App\Application\Timetable\Support\EngineTransaction;
use App\Application\Timetable\Support\TimetableBoardLoader;
use App\Domain\Timetable\Repositories\TimetableConfigurationRepositoryInterface;
use App\Domain\Timetable\ValueObjects\ActivityType;

final class SyncTimetableActivitiesHandler implements CommandHandler
{
    public function __construct(
        private readonly EngineTransaction $tx,
        private readonly TimetableBoardLoader $boards,
        private readonly TimetableConfigurationRepositoryInterface $config,
    ) {}

    public function handle(Command $command): SyncTimetableActivitiesResult
    {
        assert($command instanceof SyncTimetableActivitiesCommand);
        $board = $this->boards->load($command->schoolId, $command->academicYearId);
        $covered = [];
        foreach ($board->activities as $a) {
            $lead = array_values(array_filter($a['teachers'], static fn (array $t): bool => $t['role'] === 1))[0]['teacher_id'] ?? null;
            foreach ($a['targets'] as $t) {
                $covered[$t['section_id'].':'.$a['subject_id'].':'.$lead] = true;
            }
        }
        $todo = array_values(array_filter($board->requirements, static fn (array $r): bool => $r['weekly'] !== null && $r['weekly'] > 0
            && ! isset($covered[$r['section_id'].':'.$r['subject_id'].':'.$r['teacher_id']])
            && ($command->sectionIds === null || in_array($r['section_id'], $command->sectionIds, true))));

        return $this->tx->run(SyncTimetableActivitiesResult::class, $command->idempotencyKey, 'SyncTimetableActivities', function () use ($command, $board, $todo): SyncTimetableActivitiesResult {
            foreach ($todo as $r) {
                $practical = $board->isPractical($r['subject_id']);
                $this->config->insertActivity($command->schoolId, $command->academicYearId, [
                    'subject_id' => $r['subject_id'],
                    'activity_type' => ($practical ? ActivityType::Practical : ActivityType::Theory)->value,
                    'weekly_count' => $r['weekly'],
                    'block_length' => $practical ? min(2, $r['weekly']) : 1,
                    'distribution' => null, 'room_id' => null, 'room_type' => null, 'workshop_id' => null,
                    'week_pattern' => 0, 'term_id' => null, 'note' => null,
                ], [['section_id' => $r['section_id'], 'group_id' => null]], [['teacher_id' => $r['teacher_id'], 'role' => 1, 'sessions' => null]], $command->userId);
            }
            $this->tx->stage('configuration_changed', $command->schoolId, $command->academicYearId, null, $command->userId, ['part' => 'activity', 'op' => 'sync', 'created' => count($todo)]);

            return SyncTimetableActivitiesResult::success(null, ['created' => count($todo)]);
        });
    }
}
