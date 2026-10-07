<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Timetable\Results\SplitSectionIntoGroupsResult;
use App\Application\Timetable\Support\EngineTransaction;
use App\Domain\Timetable\Repositories\TimetableConfigurationRepositoryInterface;
use App\Domain\Timetable\Repositories\TimetableEngineReadRepositoryInterface;
use App\Domain\Timetable\Services\GroupSplitPlanner;

final class SplitSectionIntoGroupsHandler implements CommandHandler
{
    public function __construct(
        private readonly EngineTransaction $tx,
        private readonly TimetableConfigurationRepositoryInterface $config,
        private readonly TimetableEngineReadRepositoryInterface $engine,
        private readonly GroupSplitPlanner $planner,
    ) {}

    public function handle(Command $command): SplitSectionIntoGroupsResult
    {
        assert($command instanceof SplitSectionIntoGroupsCommand);
        $error = $this->planner->error($command->name, $command->groupCount, $command->capacity);
        if ($error !== null) {
            return SplitSectionIntoGroupsResult::failure($error);
        }
        if (! $this->config->referencesBelongToSchool($command->schoolId, $command->academicYearId, ['section_ids' => [$command->sectionId]])) {
            return SplitSectionIntoGroupsResult::failure('timetable.reference_not_in_school');
        }
        $members = $this->engine->sectionEnrollmentIds($command->schoolId, $command->academicYearId, $command->sectionId);
        $groups = $this->planner->plan($members, $command->groupCount, $command->capacity, $command->groupNames);
        if ($groups === null) {
            return SplitSectionIntoGroupsResult::failure('timetable.split_invalid');
        }
        $name = trim($command->name);

        return $this->tx->run(SplitSectionIntoGroupsResult::class, $command->idempotencyKey, 'SplitSectionIntoGroups', function () use ($command, $name, $groups): SplitSectionIntoGroupsResult {
            $ids = $this->config->insertDivision($command->schoolId, $command->academicYearId, $command->sectionId, $name, $groups);
            $this->tx->stage('configuration_changed', $command->schoolId, $command->academicYearId, $command->sectionId, $command->userId, ['part' => 'division', 'op' => 'split', 'groups' => count($ids)]);

            return SplitSectionIntoGroupsResult::success(null, ['group_ids' => $ids, 'sizes' => array_column($groups, 'student_count')]);
        });
    }
}
