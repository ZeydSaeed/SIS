<?php

namespace App\Application\Bank\Commands;

use App\Application\Bank\Results\ReviseQuestionResult;
use App\Application\Bank\Support\QuestionAuthorAccess;
use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\UnitOfWork;
use App\Domain\Bank\Exceptions\QuestionBankException;
use App\Domain\Bank\Repositories\QuestionRepositoryInterface;
use App\Domain\Bank\Services\QuestionReviewRules;
use App\Domain\Bank\Support\QuestionIdempotencyGuard;
use App\Domain\Bank\ValueObjects\QuestionContent;

/**
 * Changing a question after submission = a new draft version, copied from the latest one (DEC-057).
 *
 * @implements CommandHandler<ReviseQuestionCommand>
 */
final class ReviseQuestionHandler implements CommandHandler
{
    private const COMMAND_NAME = 'ReviseBankQuestion';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly QuestionRepositoryInterface $questions,
        private readonly QuestionAuthorAccess $access,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): ReviseQuestionResult
    {
        $key = QuestionIdempotencyGuard::requireKey($command->idempotencyKey);
        $fingerprint = QuestionIdempotencyGuard::fingerprint(self::COMMAND_NAME, [
            'school_id' => $command->schoolId,
            'question_id' => $command->questionId,
            'actor' => $command->actorId,
        ]);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            QuestionIdempotencyGuard::assertSameRequest($cached, $fingerprint, self::COMMAND_NAME);

            return new ReviseQuestionResult($command->questionId, (int) $cached['version_id'], true);
        }

        return $this->unitOfWork->transaction(function () use ($command, $key, $fingerprint): ReviseQuestionResult {
            $question = $this->questions->lockQuestion($command->schoolId, $command->questionId)
                ?? throw QuestionBankException::questionNotFound($command->questionId);
            QuestionReviewRules::assertQuestionActive($question['status']);
            $this->access->assertMayAuthor($question['subject_id'], $command->actorId, $command->schoolId, $command->academicYearId, $command->schoolWide);
            if ($this->questions->hasOpenVersion($command->schoolId, $question['id'])) {
                throw QuestionBankException::openVersionExists();
            }
            $latest = $this->questions->latestVersion($command->schoolId, $question['id'])
                ?? throw QuestionBankException::questionNotFound($command->questionId);

            $content = QuestionContent::fromStored($latest['type'], $latest['stem'], $latest['payload'], $latest['max_mark']);
            $versionId = $this->questions->insertVersion($question['id'], $command->schoolId, $latest['version_no'] + 1, $content, $command->actorId);
            $this->idempotency->store($key, self::COMMAND_NAME, [
                'version_id' => $versionId,
                QuestionIdempotencyGuard::FINGERPRINT => $fingerprint,
            ]);

            return new ReviseQuestionResult($question['id'], $versionId);
        });
    }
}
