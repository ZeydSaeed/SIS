<?php

namespace App\Application\Bank\Commands;

use App\Application\Bank\Results\CreateQuestionResult;
use App\Application\Bank\Support\QuestionAuthorAccess;
use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\UnitOfWork;
use App\Domain\Bank\Repositories\QuestionRepositoryInterface;
use App\Domain\Bank\Support\QuestionIdempotencyGuard;
use App\Domain\Bank\ValueObjects\QuestionContent;

/** @implements CommandHandler<CreateQuestionCommand> */
final class CreateQuestionHandler implements CommandHandler
{
    private const COMMAND_NAME = 'CreateBankQuestion';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly QuestionRepositoryInterface $questions,
        private readonly QuestionAuthorAccess $access,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): CreateQuestionResult
    {
        $key = QuestionIdempotencyGuard::requireKey($command->idempotencyKey);
        $content = QuestionContent::fromInput($command->type, $command->stem, $command->maxMark, $command->content);
        $fingerprint = QuestionIdempotencyGuard::fingerprint(self::COMMAND_NAME, [
            'school_id' => $command->schoolId,
            'subject_id' => $command->subjectId,
            'content' => $content->canonical(),
            'actor' => $command->actorId,
        ]);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            QuestionIdempotencyGuard::assertSameRequest($cached, $fingerprint, self::COMMAND_NAME);

            return new CreateQuestionResult((int) $cached['question_id'], (int) $cached['version_id'], true);
        }

        $this->access->assertMayAuthor($command->subjectId, $command->actorId, $command->schoolId, $command->academicYearId, $command->schoolWide);

        return $this->unitOfWork->transaction(function () use ($command, $content, $key, $fingerprint): CreateQuestionResult {
            $questionId = $this->questions->insertQuestion($command->schoolId, $command->subjectId, $command->actorId);
            $versionId = $this->questions->insertVersion($questionId, $command->schoolId, 1, $content, $command->actorId);
            $this->idempotency->store($key, self::COMMAND_NAME, [
                'question_id' => $questionId,
                'version_id' => $versionId,
                QuestionIdempotencyGuard::FINGERPRINT => $fingerprint,
            ]);

            return new CreateQuestionResult($questionId, $versionId);
        });
    }
}
