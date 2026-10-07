<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Timetable\Results\EndTimetableDivisionResult;
use App\Application\Timetable\Support\EngineTransaction;
use App\Domain\Timetable\Repositories\TimetableConfigurationRepositoryInterface;

final class EndTimetableDivisionHandler implements CommandHandler
{
    public function __construct(
        private readonly EngineTransaction $tx,
        private readonly TimetableConfigurationRepositoryInterface $config,
    ) {}

    public function handle(Command $command): EndTimetableDivisionResult
    {
        assert($command instanceof EndTimetableDivisionCommand);

        return $this->tx->run(EndTimetableDivisionResult::class, $command->idempotencyKey, 'EndTimetableDivision', function () use ($command): EndTimetableDivisionResult {
            if (! $this->config->endDivision($command->schoolId, $command->divisionId, (new \DateTimeImmutable)->format('Y-m-d'))) {
                return EndTimetableDivisionResult::failure('timetable.division_not_found');
            }
            $this->tx->stage('configuration_changed', $command->schoolId, $command->academicYearId, $command->divisionId, $command->userId, ['part' => 'division', 'op' => 'end']);

            return EndTimetableDivisionResult::success($command->divisionId);
        });
    }
}
