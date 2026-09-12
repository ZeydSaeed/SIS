<?php

namespace App\Application\Promotion\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Promotion\Results\CreatePromotionRuleResult;
use App\Domain\Promotion\Events\PromotionRuleCreated;
use App\Domain\Promotion\Repositories\PromotionRepositoryInterface;
use App\Domain\Promotion\Support\PromotionIdempotencyGuard;

final class CreatePromotionRuleHandler implements CommandHandler
{
    private const COMMAND_NAME = 'CreatePromotionRule';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly PromotionRepositoryInterface $promotion,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): CreatePromotionRuleResult
    {
        assert($command instanceof CreatePromotionRuleCommand);
        $key = PromotionIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return CreatePromotionRuleResult::fromIdempotency((int) $cached['rule_id']);
        }

        if ($command->fromGradeLevelId === $command->toGradeLevelId) {
            return CreatePromotionRuleResult::failure(['promotion.grade_direction_invalid']);
        }
        if (! $this->promotion->gradeLevelExists($command->fromGradeLevelId)
            || ! $this->promotion->gradeLevelExists($command->toGradeLevelId)) {
            return CreatePromotionRuleResult::failure(['promotion.grade_level_not_found']);
        }

        $at = (new \DateTimeImmutable)->format('Y-m-d H:i:s');
        $id = $this->unitOfWork->transaction(function () use ($command, $key, $at): int {
            $id = $this->promotion->createRule(
                $command->schoolId,
                $command->fromGradeLevelId,
                $command->toGradeLevelId,
                $command->minGpa,
                $command->minPassSubjects,
                $command->maxFailedSubjects,
                $command->isActive,
                $at,
            );
            $this->outbox->stage(new PromotionRuleCreated(
                $id,
                $command->schoolId,
                $command->fromGradeLevelId,
                $command->toGradeLevelId,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, ['rule_id' => $id]);

            return $id;
        });

        return CreatePromotionRuleResult::success($id);
    }
}
