<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Timetable\Results\EndTimetableActivityResult;
use App\Application\Timetable\Support\EngineTransaction;
use App\Domain\Timetable\Repositories\TimetableConfigurationRepositoryInterface;

final class EndTimetableActivityHandler implements CommandHandler
{
    public function __construct(
        private readonly EngineTransaction $tx,
        private readonly TimetableConfigurationRepositoryInterface $config,
    ) {}

    public function handle(Command $command): EndTimetableActivityResult
    {
        assert($command instanceof EndTimetableActivityCommand);
        $activity = $this->config->findActivity($command->schoolId, $command->activityId);
        if ($activity === null) {
            return EndTimetableActivityResult::failure('timetable.activity_not_found');
        }

        return $this->tx->run(EndTimetableActivityResult::class, $command->idempotencyKey, 'EndTimetableActivity', function () use ($command, $activity): EndTimetableActivityResult {
            if (! $this->config->endActivity($command->schoolId, $command->activityId, (new \DateTimeImmutable)->format('Y-m-d'))) {
                return EndTimetableActivityResult::failure('timetable.activity_not_active');
            }
            $this->tx->stage('configuration_changed', $command->schoolId, $activity['academic_year_id'], $command->activityId, $command->userId, ['part' => 'activity', 'op' => 'end']);

            return EndTimetableActivityResult::success($command->activityId);
        });
    }
}
