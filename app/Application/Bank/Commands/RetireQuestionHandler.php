<?php

namespace App\Application\Bank\Commands;

use App\Application\Bank\Results\RetireQuestionResult;
use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\UnitOfWork;
use App\Domain\Bank\Exceptions\QuestionBankException;
use App\Domain\Bank\Repositories\QuestionRepositoryInterface;
use App\Domain\Bank\Services\QuestionReviewRules;
use App\Domain\Bank\Support\QuestionIdempotencyGuard;

/** @implements CommandHandler<RetireQuestionCommand> */
final class RetireQuestionHandler implements CommandHandler
{
    private const COMMAND_NAME = 'RetireBankQuestion';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly QuestionRepositoryInterface $questions,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): RetireQuestionResult
    {
        $key = QuestionIdempotencyGuard::requireKey($command->idempotencyKey);
        $reason = trim($command->reason);
        if ($reason === '') {
            throw QuestionBankException::reasonRequired();
        }
        $fingerprint = QuestionIdempotencyGuard::fingerprint(self::COMMAND_NAME, [
            'school_id' => $command->schoolId,
            'question_id' => $command->questionId,
            'reason' => $reason,
            'actor' => $command->actorId,
        ]);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            QuestionIdempotencyGuard::assertSameRequest($cached, $fingerprint, self::COMMAND_NAME);

            return new RetireQuestionResult($command->questionId, null, true);
        }

        return $this->unitOfWork->transaction(function () use ($command, $reason, $key, $fingerprint): RetireQuestionResult {
            $question = $this->questions->lockQuestion($command->schoolId, $command->questionId)
                ?? throw QuestionBankException::questionNotFound($command->questionId);
            QuestionReviewRules::assertQuestionActive($question['status']);

            $this->questions->retire($question['id'], $command->actorId, $reason);
            $this->idempotency->store($key, self::COMMAND_NAME, [QuestionIdempotencyGuard::FINGERPRINT => $fingerprint]);

            return new RetireQuestionResult($question['id'], null);
        });
    }
}
