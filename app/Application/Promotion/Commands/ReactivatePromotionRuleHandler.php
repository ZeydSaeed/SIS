<?php

namespace App\Application\Promotion\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Promotion\Results\ReactivatePromotionRuleResult;
use App\Domain\Promotion\Events\PromotionRuleReactivated;
use App\Domain\Promotion\Repositories\PromotionRepositoryInterface;
use App\Domain\Promotion\Support\PromotionIdempotencyGuard;

final class ReactivatePromotionRuleHandler implements CommandHandler
{
    private const COMMAND_NAME = 'ReactivatePromotionRule';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly PromotionRepositoryInterface $promotion,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): ReactivatePromotionRuleResult
    {
        assert($command instanceof ReactivatePromotionRuleCommand);
        $key = PromotionIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return ReactivatePromotionRuleResult::fromIdempotency((int) $cached['rule_id']);
        }

        $row = $this->promotion->findRule($command->schoolId, $command->ruleId);
        if ($row === null) {
            return ReactivatePromotionRuleResult::failure(['promotion.rule_not_found']);
        }
        if ($row->isActive) {
            return ReactivatePromotionRuleResult::failure(['promotion.rule_already_active']);
        }

        $this->unitOfWork->transaction(function () use ($command, $key): void {
            $this->promotion->setRuleActive($command->schoolId, $command->ruleId, true);
            $this->outbox->stage(new PromotionRuleReactivated(
                $command->ruleId,
                $command->schoolId,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, ['rule_id' => $command->ruleId]);
        });

        return ReactivatePromotionRuleResult::success($command->ruleId);
    }
}
