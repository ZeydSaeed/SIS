<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Timetable\Results\SaveTimetableDisplayResult;
use App\Application\Timetable\Support\EngineTransaction;
use App\Domain\Timetable\Repositories\TimetableConfigurationRepositoryInterface;
use App\Domain\Timetable\Support\TimetableDisplaySettings;

final class SaveTimetableDisplayHandler implements CommandHandler
{
    public function __construct(
        private readonly EngineTransaction $tx,
        private readonly TimetableConfigurationRepositoryInterface $config,
    ) {}

    public function handle(Command $command): SaveTimetableDisplayResult
    {
        assert($command instanceof SaveTimetableDisplayCommand);
        $display = TimetableDisplaySettings::from($command->display)->toArray();

        return $this->tx->run(SaveTimetableDisplayResult::class, $command->idempotencyKey, 'SaveTimetableDisplay', function () use ($command, $display): SaveTimetableDisplayResult {
            $this->config->saveDisplay($command->schoolId, $command->academicYearId, $display, $command->userId);
            $this->tx->stage('configuration_changed', $command->schoolId, $command->academicYearId, null, $command->userId, ['part' => 'display']);

            return SaveTimetableDisplayResult::success(null, $display);
        });
    }
}
