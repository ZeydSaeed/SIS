<?php

namespace App\Application\Promotion\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Promotion\Results\DeactivatePromotionRuleResult;
use App\Domain\Promotion\Events\PromotionRuleDeactivated;
use App\Domain\Promotion\Repositories\PromotionRepositoryInterface;
use App\Domain\Promotion\Support\PromotionIdempotencyGuard;

final class DeactivatePromotionRuleHandler implements CommandHandler
{
    private const COMMAND_NAME = 'DeactivatePromotionRule';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly PromotionRepositoryInterface $promotion,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): DeactivatePromotionRuleResult
    {
        assert($command instanceof DeactivatePromotionRuleCommand);
        $key = PromotionIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return DeactivatePromotionRuleResult::fromIdempotency((int) $cached['rule_id']);
        }

        $row = $this->promotion->findRule($command->schoolId, $command->ruleId);
        if ($row === null) {
            return DeactivatePromotionRuleResult::failure(['promotion.rule_not_found']);
        }
        if (! $row->isActive) {
            return DeactivatePromotionRuleResult::failure(['promotion.rule_already_inactive']);
        }

        $this->unitOfWork->transaction(function () use ($command, $key): void {
            $this->promotion->setRuleActive($command->schoolId, $command->ruleId, false);
            $this->outbox->stage(new PromotionRuleDeactivated(
                $command->ruleId,
                $command->schoolId,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, ['rule_id' => $command->ruleId]);
        });

        return DeactivatePromotionRuleResult::success($command->ruleId);
    }
}
