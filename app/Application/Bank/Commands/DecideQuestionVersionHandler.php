<?php

namespace App\Application\Bank\Commands;

use App\Application\Bank\Results\DecideQuestionVersionResult;
use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\UnitOfWork;
use App\Domain\Bank\Exceptions\QuestionBankException;
use App\Domain\Bank\Repositories\QuestionRepositoryInterface;
use App\Domain\Bank\Services\QuestionReviewRules;
use App\Domain\Bank\Support\QuestionIdempotencyGuard;
use App\Domain\Bank\ValueObjects\QuestionVersionStatus;

/**
 * Approving supersedes the previous approved version in the same transaction (one approved version per question).
 *
 * @implements CommandHandler<DecideQuestionVersionCommand>
 */
final class DecideQuestionVersionHandler implements CommandHandler
{
    private const COMMAND_NAME = 'DecideBankQuestionVersion';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly QuestionRepositoryInterface $questions,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): DecideQuestionVersionResult
    {
        $key = QuestionIdempotencyGuard::requireKey($command->idempotencyKey);
        $note = trim($command->note);
        $fingerprint = QuestionIdempotencyGuard::fingerprint(self::COMMAND_NAME, [
            'school_id' => $command->schoolId,
            'version_id' => $command->versionId,
            'decision' => $command->decision,
            'note' => $note,
            'actor' => $command->actorId,
        ]);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            QuestionIdempotencyGuard::assertSameRequest($cached, $fingerprint, self::COMMAND_NAME);

            return new DecideQuestionVersionResult((int) $cached['question_id'], $command->versionId, true);
        }

        return $this->unitOfWork->transaction(function () use ($command, $note, $key, $fingerprint): DecideQuestionVersionResult {
            $version = $this->questions->lockVersion($command->schoolId, $command->versionId)
                ?? throw QuestionBankException::versionNotFound($command->versionId);
            $question = $this->questions->lockQuestion($command->schoolId, $version['question_id'])
                ?? throw QuestionBankException::questionNotFound($version['question_id']);
            QuestionReviewRules::assertQuestionActive($question['status']);
            QuestionReviewRules::assertCanDecide(QuestionVersionStatus::from($version['status']), $version['created_by'], $command->actorId, $command->decision, $note);

            if ($command->decision === QuestionReviewRules::APPROVE) {
                $previous = $this->questions->approvedVersionId($command->schoolId, $question['id']);
                if ($previous !== null) {
                    $this->questions->supersede($previous);
                }
                $this->questions->approve($version['id'], $command->actorId, $note);
            } else {
                $this->questions->reject($version['id'], $command->actorId, $note);
            }
            $this->idempotency->store($key, self::COMMAND_NAME, [
                'question_id' => $question['id'],
                QuestionIdempotencyGuard::FINGERPRINT => $fingerprint,
            ]);

            return new DecideQuestionVersionResult($question['id'], $version['id']);
        });
    }
}
