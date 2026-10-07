<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Timetable\Results\UpdateTimetableActivityResult;
use App\Application\Timetable\Support\ActivityGuard;
use App\Application\Timetable\Support\EngineTransaction;
use App\Domain\Timetable\Repositories\TimetableConfigurationRepositoryInterface;

final class UpdateTimetableActivityHandler implements CommandHandler
{
    public function __construct(
        private readonly EngineTransaction $tx,
        private readonly ActivityGuard $guard,
        private readonly TimetableConfigurationRepositoryInterface $config,
    ) {}

    public function handle(Command $command): UpdateTimetableActivityResult
    {
        assert($command instanceof UpdateTimetableActivityCommand);
        $activity = $this->config->findActivity($command->schoolId, $command->activityId);
        if ($activity === null || $activity['status'] !== 1) {
            return UpdateTimetableActivityResult::failure('timetable.activity_not_found');
        }
        $errors = $this->guard->errors($command->schoolId, $activity['academic_year_id'], $command->data(), null, null);
        if ($errors !== []) {
            return UpdateTimetableActivityResult::failure(...$errors);
        }

        return $this->tx->run(UpdateTimetableActivityResult::class, $command->idempotencyKey, 'UpdateTimetableActivity', function () use ($command, $activity): UpdateTimetableActivityResult {
            if (! $this->config->updateActivity($command->schoolId, $command->activityId, $command->data())) {
                return UpdateTimetableActivityResult::failure('timetable.activity_not_found');
            }
            $this->tx->stage('configuration_changed', $command->schoolId, $activity['academic_year_id'], $command->activityId, $command->userId, ['part' => 'activity', 'op' => 'update']);

            return UpdateTimetableActivityResult::success($command->activityId);
        });
    }
}
