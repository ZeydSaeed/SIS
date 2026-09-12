<?php

namespace App\Application\Promotion\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Promotion\Results\RecordPromotionDecisionResult;
use App\Domain\Promotion\Events\PromotionDecisionRecorded;
use App\Domain\Promotion\Repositories\PromotionRepositoryInterface;
use App\Domain\Promotion\Support\PromotionIdempotencyGuard;
use App\Domain\Promotion\ValueObjects\PromotionStatus;

final class RecordPromotionDecisionHandler implements CommandHandler
{
    private const COMMAND_NAME = 'RecordPromotionDecision';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly PromotionRepositoryInterface $promotion,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): RecordPromotionDecisionResult
    {
        assert($command instanceof RecordPromotionDecisionCommand);
        $key = PromotionIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return RecordPromotionDecisionResult::fromIdempotency((int) $cached['record_id']);
        }

        if (! PromotionStatus::isValid($command->promotionStatus)) {
            return RecordPromotionDecisionResult::failure(['promotion.status_invalid']);
        }

        if (! $this->promotion->enrollmentBelongsToSchoolYear(
            $command->enrollmentId,
            $command->schoolId,
            $command->academicYearId,
        )) {
            return RecordPromotionDecisionResult::failure(['promotion.enrollment_not_found']);
        }

        $fromGrade = $this->promotion->findEnrollmentGradeLevelId(
            $command->enrollmentId,
            $command->schoolId,
            $command->academicYearId,
        );
        if ($fromGrade === null) {
            return RecordPromotionDecisionResult::failure(['promotion.enrollment_not_found']);
        }

        if (! $this->promotion->gradeLevelExists($command->toGradeLevelId)) {
            return RecordPromotionDecisionResult::failure(['promotion.grade_level_not_found']);
        }

        if ($command->promotionStatus === PromotionStatus::Promoted
            && $command->toGradeLevelId === $fromGrade) {
            return RecordPromotionDecisionResult::failure(['promotion.grade_direction_invalid']);
        }

        $existing = $this->promotion->findDecisionId(
            $command->schoolId,
            $command->enrollmentId,
            $command->academicYearId,
        );
        if ($existing !== null) {
            return RecordPromotionDecisionResult::failure(['promotion.decision_exists']);
        }

        $at = (new \DateTimeImmutable)->format('Y-m-d H:i:s');
        $id = $this->unitOfWork->transaction(function () use ($command, $key, $fromGrade, $at): int {
            $id = $this->promotion->createRecord(
                $command->schoolId,
                $command->enrollmentId,
                $command->academicYearId,
                $fromGrade,
                $command->toGradeLevelId,
                $command->promotionStatus,
                $command->gpaAtPromotion,
                $command->decidedBy,
                $at,
                $command->notes !== null ? trim($command->notes) : null,
                $at,
            );
            $this->outbox->stage(new PromotionDecisionRecorded(
                $id,
                $command->schoolId,
                $command->enrollmentId,
                $command->academicYearId,
                $command->promotionStatus,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, ['record_id' => $id]);

            return $id;
        });

        return RecordPromotionDecisionResult::success($id);
    }
}
