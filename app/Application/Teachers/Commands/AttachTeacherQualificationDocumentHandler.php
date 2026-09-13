<?php

namespace App\Application\Teachers\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Teachers\Results\AttachTeacherQualificationDocumentResult;
use App\Domain\Teachers\Events\TeacherQualificationDocumentAttached;
use App\Domain\Teachers\Repositories\TeacherRepositoryInterface;
use App\Domain\Teachers\Services\AttachQualificationDocumentGuard;
use App\Domain\Teachers\Support\TeacherIdempotencyGuard;

final class AttachTeacherQualificationDocumentHandler implements CommandHandler
{
    private const COMMAND_NAME = 'AttachTeacherQualificationDocument';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly TeacherRepositoryInterface $teachers,
        private readonly AttachQualificationDocumentGuard $guard,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): AttachTeacherQualificationDocumentResult
    {
        assert($command instanceof AttachTeacherQualificationDocumentCommand);
        $key = TeacherIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return AttachTeacherQualificationDocumentResult::fromIdempotency(
                (int) $cached['qualification_id'],
                (string) $cached['storage_key'],
            );
        }

        [$error, , $doc] = $this->guard->evaluate(
            $command->schoolId,
            $command->teacherId,
            $command->qualificationId,
            $command->documentId,
            $command->academicYearId,
        );
        if ($error !== null || $doc === null) {
            return AttachTeacherQualificationDocumentResult::failure([$error ?? 'teachers.qualification_attach_failed']);
        }

        $storageKey = $doc->storageKey;
        $this->unitOfWork->transaction(function () use ($command, $key, $storageKey): void {
            $this->teachers->setQualificationDocumentStorageKey(
                $command->teacherId,
                $command->qualificationId,
                $storageKey,
            );
            $this->outbox->stage(new TeacherQualificationDocumentAttached(
                $command->qualificationId,
                $command->teacherId,
                $command->schoolId,
                $command->documentId,
                $storageKey,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, [
                'qualification_id' => $command->qualificationId,
                'storage_key' => $storageKey,
            ]);
        });

        return AttachTeacherQualificationDocumentResult::success($command->qualificationId, $storageKey);
    }
}
