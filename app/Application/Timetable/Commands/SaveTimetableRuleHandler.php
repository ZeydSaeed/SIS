<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Timetable\Results\SaveTimetableRuleResult;
use App\Application\Timetable\Support\EngineTransaction;
use App\Domain\Timetable\Constraints\ConstraintRuleCatalogue;
use App\Domain\Timetable\Repositories\TimetableConfigurationRepositoryInterface;

final class SaveTimetableRuleHandler implements CommandHandler
{
    public function __construct(
        private readonly EngineTransaction $tx,
        private readonly TimetableConfigurationRepositoryInterface $config,
    ) {}

    public function handle(Command $command): SaveTimetableRuleResult
    {
        assert($command instanceof SaveTimetableRuleCommand);
        $scope = array_map(static fn ($v): ?int => $v === null || $v === '' ? null : (int) $v, array_intersect_key($command->scope, array_flip(ConstraintRuleCatalogue::SCOPES)));
        $errors = ConstraintRuleCatalogue::validate($command->ruleType, $scope, $command->params, $command->priority);
        if ($errors !== []) {
            return SaveTimetableRuleResult::failure(...$errors);
        }
        $params = ConstraintRuleCatalogue::normaliseParams($command->ruleType, $command->params);
        $refs = [];
        foreach ($scope as $column => $id) {
            $refs[($column === 'other_activity_id' ? 'activity_id' : $column).'s'][] = $id;
        }
        if (isset($params['other_teacher_id'])) {
            $refs['teacher_ids'][] = $params['other_teacher_id'];
        }
        if (! $this->config->referencesBelongToSchool($command->schoolId, $command->academicYearId, $refs)) {
            return SaveTimetableRuleResult::failure('timetable.reference_not_in_school');
        }

        return $this->tx->run(SaveTimetableRuleResult::class, $command->idempotencyKey, 'SaveTimetableRule', function () use ($command, $scope, $params): SaveTimetableRuleResult {
            $id = $this->config->insertRule($command->schoolId, $command->academicYearId, $command->ruleType, $command->priority, $scope, $params, $command->reason, $command->userId);
            $this->tx->stage('configuration_changed', $command->schoolId, $command->academicYearId, $id, $command->userId, ['part' => 'rule', 'op' => 'create', 'rule_type' => $command->ruleType]);

            return SaveTimetableRuleResult::success($id);
        });
    }
}
