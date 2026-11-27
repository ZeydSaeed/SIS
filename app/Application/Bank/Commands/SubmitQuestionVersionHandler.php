<?php

namespace App\Application\Bank\Commands;

use App\Application\Bank\Results\SubmitQuestionVersionResult;
use App\Application\Bank\Support\QuestionAuthorAccess;
use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\UnitOfWork;
use App\Domain\Bank\Exceptions\QuestionBankException;
use App\Domain\Bank\Repositories\QuestionRepositoryInterface;
use App\Domain\Bank\Services\QuestionReviewRules;
use App\Domain\Bank\Support\QuestionIdempotencyGuard;
use App\Domain\Bank\ValueObjects\QuestionVersionStatus;

/** @implements CommandHandler<SubmitQuestionVersionCommand> */
final class SubmitQuestionVersionHandler implements CommandHandler
{
    private const COMMAND_NAME = 'SubmitBankQuestionVersion';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly QuestionRepositoryInterface $questions,
        private readonly QuestionAuthorAccess $access,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): SubmitQuestionVersionResult
    {
        $key = QuestionIdempotencyGuard::requireKey($command->idempotencyKey);
        $fingerprint = QuestionIdempotencyGuard::fingerprint(self::COMMAND_NAME, [
            'school_id' => $command->schoolId,
            'version_id' => $command->versionId,
            'actor' => $command->actorId,
        ]);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            QuestionIdempotencyGuard::assertSameRequest($cached, $fingerprint, self::COMMAND_NAME);

            return new SubmitQuestionVersionResult((int) $cached['question_id'], $command->versionId, true);
        }

        return $this->unitOfWork->transaction(function () use ($command, $key, $fingerprint): SubmitQuestionVersionResult {
            $version = $this->questions->lockVersion($command->schoolId, $command->versionId)
                ?? throw QuestionBankException::versionNotFound($command->versionId);
            $question = $this->questions->lockQuestion($command->schoolId, $version['question_id'])
                ?? throw QuestionBankException::questionNotFound($version['question_id']);
            QuestionReviewRules::assertQuestionActive($question['status']);
            QuestionReviewRules::assertAuthorCanChange(QuestionVersionStatus::from($version['status']), $version['created_by'], $command->actorId);
            $this->access->assertMayAuthor($question['subject_id'], $command->actorId, $command->schoolId, $command->academicYearId, $command->schoolWide);

            $this->questions->submit($version['id'], $command->actorId);
            $this->idempotency->store($key, self::COMMAND_NAME, [
                'question_id' => $question['id'],
                QuestionIdempotencyGuard::FINGERPRINT => $fingerprint,
            ]);

            return new SubmitQuestionVersionResult($question['id'], $version['id']);
        });
    }
}
