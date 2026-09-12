<?php

namespace App\Application\Teachers\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Teachers\Results\VoidTeacherQualificationResult;
use App\Domain\Teachers\Events\TeacherQualificationVoided;
use App\Domain\Teachers\Repositories\TeacherRepositoryInterface;
use App\Domain\Teachers\Support\TeacherIdempotencyGuard;
use App\Domain\Teachers\ValueObjects\QualificationStatus;

final class VoidTeacherQualificationHandler implements CommandHandler
{
    private const COMMAND_NAME = 'VoidTeacherQualification';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly TeacherRepositoryInterface $teachers,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): VoidTeacherQualificationResult
    {
        assert($command instanceof VoidTeacherQualificationCommand);
        $key = TeacherIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return VoidTeacherQualificationResult::fromIdempotency((int) $cached['qualification_id']);
        }

        if (! $this->teachers->belongsToSchool($command->teacherId, $command->schoolId, $command->academicYearId)) {
            return VoidTeacherQualificationResult::failure(['teachers.not_in_school_year']);
        }

        $qual = $this->teachers->findQualification($command->teacherId, $command->qualificationId);
        if ($qual === null) {
            return VoidTeacherQualificationResult::failure(['teachers.qualification_not_found']);
        }
        if (! QualificationStatus::isActive($qual->status)) {
            return VoidTeacherQualificationResult::failure(['teachers.qualification_not_active']);
        }

        $at = (new \DateTimeImmutable)->format('Y-m-d H:i:s');
        $ok = $this->unitOfWork->transaction(function () use ($command, $key, $at): bool {
            $updated = $this->teachers->voidQualification(
                $command->teacherId,
                $command->qualificationId,
                $at,
            );
            if (! $updated) {
                return false;
            }
            $this->outbox->stage(new TeacherQualificationVoided(
                $command->qualificationId,
                $command->teacherId,
                $command->schoolId,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, [
                'qualification_id' => $command->qualificationId,
            ]);

            return true;
        });

        if (! $ok) {
            return VoidTeacherQualificationResult::failure(['teachers.qualification_void_failed']);
        }

        return VoidTeacherQualificationResult::success($command->qualificationId);
    }
}
