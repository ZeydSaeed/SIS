<?php

namespace App\Application\Teachers\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Teachers\Results\AssignTeacherSubjectResult;
use App\Domain\Teachers\Events\TeacherSubjectAssigned;
use App\Domain\Teachers\Repositories\TeacherRepositoryInterface;
use App\Domain\Teachers\Support\TeacherIdempotencyGuard;

final class AssignTeacherSubjectHandler implements CommandHandler
{
    private const COMMAND_NAME = 'AssignTeacherSubject';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly TeacherRepositoryInterface $teachers,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): AssignTeacherSubjectResult
    {
        assert($command instanceof AssignTeacherSubjectCommand);
        $key = TeacherIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return AssignTeacherSubjectResult::fromIdempotency((int) $cached['assignment_id']);
        }

        if (! $this->teachers->belongsToSchool($command->teacherId, $command->schoolId, $command->academicYearId)) {
            return AssignTeacherSubjectResult::failure(['teachers.not_in_school_year']);
        }
        if (! $this->teachers->subjectExists($command->subjectId)) {
            return AssignTeacherSubjectResult::failure(['teachers.subject_not_found']);
        }

        $existing = $this->teachers->findSubjectAssignmentId(
            $command->teacherId,
            $command->subjectId,
            $command->academicYearId,
            $command->schoolId,
        );
        if ($existing !== null) {
            $this->idempotency->store($key, self::COMMAND_NAME, ['assignment_id' => $existing]);

            return AssignTeacherSubjectResult::fromIdempotency($existing);
        }

        $at = (new \DateTimeImmutable)->format('Y-m-d H:i:s');
        $id = $this->unitOfWork->transaction(function () use ($command, $key, $at): int {
            $id = $this->teachers->assignSubject(
                $command->teacherId,
                $command->subjectId,
                $command->academicYearId,
                $command->schoolId,
                $at,
            );
            $this->outbox->stage(new TeacherSubjectAssigned(
                $id,
                $command->teacherId,
                $command->subjectId,
                $command->schoolId,
                $command->academicYearId,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, ['assignment_id' => $id]);

            return $id;
        });

        return AssignTeacherSubjectResult::success($id);
    }
}
