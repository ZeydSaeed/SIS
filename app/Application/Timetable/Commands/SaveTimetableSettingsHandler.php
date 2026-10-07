<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Timetable\Results\SaveTimetableSettingsResult;
use App\Application\Timetable\Support\EngineTransaction;
use App\Domain\Timetable\Repositories\TimetableConfigurationRepositoryInterface;
use App\Domain\Timetable\Support\TimetableSettings;

final class SaveTimetableSettingsHandler implements CommandHandler
{
    public function __construct(
        private readonly EngineTransaction $tx,
        private readonly TimetableConfigurationRepositoryInterface $config,
    ) {}

    public function handle(Command $command): SaveTimetableSettingsResult
    {
        assert($command instanceof SaveTimetableSettingsCommand);
        $days = array_values(array_unique(array_map('intval', $command->days)));
        sort($days);
        if ($days === [] || min($days) < 1 || max($days) > 7) {
            return SaveTimetableSettingsResult::failure('timetable.settings_days_invalid');
        }
        $weights = [];
        foreach ($command->weights as $priority => $weight) {
            if ((int) $priority >= 2 && (int) $priority <= 6 && (int) $weight > 0) {
                $weights[(int) $priority] = min(1_000_000, (int) $weight);
            }
        }
        $settings = new TimetableSettings($days, $command->cycleWeeks, $command->maxTeacherPerDay, $command->maxSubjectPerDay, $command->doubleChangeoverMinutes, $weights);

        return $this->tx->run(SaveTimetableSettingsResult::class, $command->idempotencyKey, 'SaveTimetableSettings', function () use ($command, $settings): SaveTimetableSettingsResult {
            $this->config->saveSettings($command->schoolId, $command->academicYearId, $settings, $command->userId);
            $this->tx->stage('configuration_changed', $command->schoolId, $command->academicYearId, null, $command->userId, ['part' => 'settings'] + $settings->toArray());

            return SaveTimetableSettingsResult::success(null, $settings->toArray());
        });
    }
}
