<?php

namespace App\Application\Curriculum\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Curriculum\Results\UpdateSubjectResult;
use App\Domain\Curriculum\Events\SubjectUpdated;
use App\Domain\Curriculum\Repositories\SubjectRepositoryInterface;
use App\Domain\Curriculum\Services\UpdateSubjectGuard;
use App\Domain\Curriculum\Support\CurriculumIdempotencyGuard;
use App\Domain\Curriculum\ValueObjects\SubjectTypeSet;
use App\Domain\Shared\ValueObjects\DisplayAppearance;

final class UpdateSubjectHandler implements CommandHandler
{
    private const COMMAND_NAME = 'UpdateSubject';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly SubjectRepositoryInterface $subjects,
        private readonly UpdateSubjectGuard $guard,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): UpdateSubjectResult
    {
        assert($command instanceof UpdateSubjectCommand);
        $key = CurriculumIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return UpdateSubjectResult::fromIdempotency((int) $cached['subject_id']);
        }

        $fields = $command->fields;
        // «نوع المادة»: the new set with its hours (or the one type an older caller sends); its first type is the
        // primary one, and the subject's hours become the types' total when any are given.
        $types = null;
        if (array_key_exists('subject_types', $fields) || array_key_exists('subject_type', $fields)) {
            $types = SubjectTypeSet::from(
                (array) ($fields['subject_types'] ?? [$fields['subject_type']]),
                (array) ($fields['subject_type_hours'] ?? []),
            );
            if ($types === null) {
                return UpdateSubjectResult::failure(['curriculum.subject_type_invalid']);
            }
            $fields['subject_type'] = $types->primary();
            if ($types->totalHours() !== null) {
                $fields['credit_hours'] = $types->totalHours();
            }
        }
        unset($fields['subject_types'], $fields['subject_type_hours']);

        $error = $this->guard->rejectionCode($command->subjectId, $fields);
        if ($error !== null) {
            return UpdateSubjectResult::failure([$error]);
        }

        if (array_key_exists('abbreviation', $fields)) {
            $fields['abbreviation'] = DisplayAppearance::of($fields['abbreviation'], null)->abbreviation;
        }

        $this->unitOfWork->transaction(function () use ($command, $key, $fields, $types): void {
            $this->subjects->updateActive(
                $command->subjectId,
                $fields,
                (new \DateTimeImmutable)->format(\DateTimeInterface::ATOM),
            );
            if ($types !== null) {
                $this->subjects->replaceTypes($command->subjectId, $types->hours);
            }
            $this->outbox->stage(new SubjectUpdated($command->subjectId, new \DateTimeImmutable));
            $this->idempotency->store($key, self::COMMAND_NAME, ['subject_id' => $command->subjectId]);
        });

        return UpdateSubjectResult::success($command->subjectId);
    }
}
