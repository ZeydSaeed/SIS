<?php

namespace App\Application\Bank\Commands;

use App\Application\Bank\Results\UpdateQuestionDraftResult;
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
use App\Domain\Bank\ValueObjects\QuestionVersionStatus;

/** @implements CommandHandler<UpdateQuestionDraftCommand> */
final class UpdateQuestionDraftHandler implements CommandHandler
{
    private const COMMAND_NAME = 'UpdateBankQuestionDraft';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly QuestionRepositoryInterface $questions,
        private readonly QuestionAuthorAccess $access,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): UpdateQuestionDraftResult
    {
        $key = QuestionIdempotencyGuard::requireKey($command->idempotencyKey);
        $content = QuestionContent::fromInput($command->type, $command->stem, $command->maxMark, $command->content);
        $fingerprint = QuestionIdempotencyGuard::fingerprint(self::COMMAND_NAME, [
            'school_id' => $command->schoolId,
            'version_id' => $command->versionId,
            'content' => $content->canonical(),
            'actor' => $command->actorId,
        ]);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            QuestionIdempotencyGuard::assertSameRequest($cached, $fingerprint, self::COMMAND_NAME);

            return new UpdateQuestionDraftResult((int) $cached['question_id'], $command->versionId, true);
        }

        return $this->unitOfWork->transaction(function () use ($command, $content, $key, $fingerprint): UpdateQuestionDraftResult {
            [$question, $version] = $this->lockDraft($command->schoolId, $command->versionId);
            QuestionReviewRules::assertAuthorCanChange(QuestionVersionStatus::from($version['status']), $version['created_by'], $command->actorId);
            $this->access->assertMayAuthor($question['subject_id'], $command->actorId, $command->schoolId, $command->academicYearId, $command->schoolWide);

            $this->questions->updateDraft($version['id'], $content);
            $this->idempotency->store($key, self::COMMAND_NAME, [
                'question_id' => $question['id'],
                QuestionIdempotencyGuard::FINGERPRINT => $fingerprint,
            ]);

            return new UpdateQuestionDraftResult($question['id'], $version['id']);
        });
    }

    /** @return array{0: array{id: int, subject_id: int, status: string}, 1: array{id: int, status: string, created_by: int}} */
    private function lockDraft(int $schoolId, int $versionId): array
    {
        $version = $this->questions->lockVersion($schoolId, $versionId)
            ?? throw QuestionBankException::versionNotFound($versionId);
        $question = $this->questions->lockQuestion($schoolId, $version['question_id'])
            ?? throw QuestionBankException::questionNotFound($version['question_id']);
        QuestionReviewRules::assertQuestionActive($question['status']);

        return [$question, $version];
    }
}
