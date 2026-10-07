<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Timetable\Results\CreateTimetableActivityResult;
use App\Application\Timetable\Support\ActivityGuard;
use App\Application\Timetable\Support\EngineTransaction;
use App\Domain\Timetable\Repositories\TimetableConfigurationRepositoryInterface;

final class CreateTimetableActivityHandler implements CommandHandler
{
    public function __construct(
        private readonly EngineTransaction $tx,
        private readonly ActivityGuard $guard,
        private readonly TimetableConfigurationRepositoryInterface $config,
    ) {}

    public function handle(Command $command): CreateTimetableActivityResult
    {
        assert($command instanceof CreateTimetableActivityCommand);
        $errors = $this->guard->errors($command->schoolId, $command->academicYearId, $command->data(), $command->targets, $command->teachers);
        if ($errors !== []) {
            return CreateTimetableActivityResult::failure(...$errors);
        }

        return $this->tx->run(CreateTimetableActivityResult::class, $command->idempotencyKey, 'CreateTimetableActivity', function () use ($command): CreateTimetableActivityResult {
            $id = $this->config->insertActivity($command->schoolId, $command->academicYearId, $command->data(), $command->targets, $command->teachers, $command->userId);
            $this->tx->stage('configuration_changed', $command->schoolId, $command->academicYearId, $id, $command->userId, ['part' => 'activity', 'op' => 'create']);

            return CreateTimetableActivityResult::success($id);
        });
    }
}
