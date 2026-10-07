<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Timetable\Results\EndTimetableRuleResult;
use App\Application\Timetable\Support\EngineTransaction;
use App\Domain\Timetable\Repositories\TimetableConfigurationRepositoryInterface;

final class EndTimetableRuleHandler implements CommandHandler
{
    public function __construct(
        private readonly EngineTransaction $tx,
        private readonly TimetableConfigurationRepositoryInterface $config,
    ) {}

    public function handle(Command $command): EndTimetableRuleResult
    {
        assert($command instanceof EndTimetableRuleCommand);

        return $this->tx->run(EndTimetableRuleResult::class, $command->idempotencyKey, 'EndTimetableRule', function () use ($command): EndTimetableRuleResult {
            if (! $this->config->endRule($command->schoolId, $command->ruleId, (new \DateTimeImmutable)->format('Y-m-d'))) {
                return EndTimetableRuleResult::failure('timetable.rule_not_found');
            }
            $this->tx->stage('configuration_changed', $command->schoolId, $command->academicYearId, $command->ruleId, $command->userId, ['part' => 'rule', 'op' => 'end']);

            return EndTimetableRuleResult::success($command->ruleId);
        });
    }
}
