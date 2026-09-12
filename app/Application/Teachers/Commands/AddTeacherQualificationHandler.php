<?php

namespace App\Application\Teachers\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Teachers\Results\AddTeacherQualificationResult;
use App\Domain\Teachers\Events\TeacherQualificationAdded;
use App\Domain\Teachers\Repositories\TeacherRepositoryInterface;
use App\Domain\Teachers\Support\TeacherIdempotencyGuard;
use App\Domain\Teachers\ValueObjects\QualificationType;

final class AddTeacherQualificationHandler implements CommandHandler
{
    private const COMMAND_NAME = 'AddTeacherQualification';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly TeacherRepositoryInterface $teachers,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): AddTeacherQualificationResult
    {
        assert($command instanceof AddTeacherQualificationCommand);
        $key = TeacherIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return AddTeacherQualificationResult::fromIdempotency((int) $cached['qualification_id']);
        }

        if (! QualificationType::isValid($command->qualificationType)) {
            return AddTeacherQualificationResult::failure(['teachers.qualification_type_invalid']);
        }

        $title = trim($command->title);
        if ($title === '') {
            return AddTeacherQualificationResult::failure(['teachers.qualification_title_required']);
        }

        if (! $this->teachers->belongsToSchool($command->teacherId, $command->schoolId, $command->academicYearId)) {
            return AddTeacherQualificationResult::failure(['teachers.not_in_school_year']);
        }

        $at = (new \DateTimeImmutable)->format('Y-m-d H:i:s');
        $id = $this->unitOfWork->transaction(function () use ($command, $key, $title, $at): int {
            $id = $this->teachers->addQualification(
                $command->teacherId,
                $command->qualificationType,
                $title,
                $command->institution !== null ? trim($command->institution) : null,
                $command->yearObtained,
                $command->documentStorageKey !== null ? trim($command->documentStorageKey) : null,
                $at,
            );
            $this->outbox->stage(new TeacherQualificationAdded(
                $id,
                $command->teacherId,
                $command->schoolId,
                $command->qualificationType,
                $title,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, ['qualification_id' => $id]);

            return $id;
        });

        return AddTeacherQualificationResult::success($id);
    }
}
