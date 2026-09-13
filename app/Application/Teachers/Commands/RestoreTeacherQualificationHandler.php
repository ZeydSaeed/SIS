<?php

namespace App\Application\Teachers\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Teachers\Results\RestoreTeacherQualificationResult;
use App\Domain\Teachers\Events\TeacherQualificationRestored;
use App\Domain\Teachers\Repositories\TeacherRepositoryInterface;
use App\Domain\Teachers\Support\TeacherIdempotencyGuard;
use App\Domain\Teachers\ValueObjects\QualificationStatus;

final class RestoreTeacherQualificationHandler implements CommandHandler
{
    private const COMMAND_NAME = 'RestoreTeacherQualification';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly TeacherRepositoryInterface $teachers,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): RestoreTeacherQualificationResult
    {
        assert($command instanceof RestoreTeacherQualificationCommand);
        $key = TeacherIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return RestoreTeacherQualificationResult::fromIdempotency((int) $cached['qualification_id']);
        }

        if (! $this->teachers->belongsToSchool($command->teacherId, $command->schoolId, $command->academicYearId)) {
            return RestoreTeacherQualificationResult::failure(['teachers.not_in_school_year']);
        }

        $qual = $this->teachers->findQualification($command->teacherId, $command->qualificationId);
        if ($qual === null) {
            return RestoreTeacherQualificationResult::failure(['teachers.qualification_not_found']);
        }
        if ($qual->status !== QualificationStatus::Voided) {
            return RestoreTeacherQualificationResult::failure(['teachers.qualification_not_voided']);
        }

        $ok = $this->unitOfWork->transaction(function () use ($command, $key): bool {
            $updated = $this->teachers->restoreQualification(
                $command->teacherId,
                $command->qualificationId,
            );
            if (! $updated) {
                return false;
            }
            $this->outbox->stage(new TeacherQualificationRestored(
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
            return RestoreTeacherQualificationResult::failure(['teachers.qualification_restore_failed']);
        }

        return RestoreTeacherQualificationResult::success($command->qualificationId);
    }
}
