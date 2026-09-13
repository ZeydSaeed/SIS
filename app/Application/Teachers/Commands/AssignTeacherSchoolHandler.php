<?php

namespace App\Application\Teachers\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Teachers\Results\AssignTeacherSchoolResult;
use App\Domain\Teachers\Events\TeacherSchoolAssigned;
use App\Domain\Teachers\Repositories\TeacherRepositoryInterface;
use App\Domain\Teachers\Services\AssignTeacherSchoolGuard;
use App\Domain\Teachers\Support\TeacherIdempotencyGuard;

final class AssignTeacherSchoolHandler implements CommandHandler
{
    private const COMMAND_NAME = 'AssignTeacherSchool';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly TeacherRepositoryInterface $teachers,
        private readonly AssignTeacherSchoolGuard $guard,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): AssignTeacherSchoolResult
    {
        assert($command instanceof AssignTeacherSchoolCommand);
        $key = TeacherIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return AssignTeacherSchoolResult::fromIdempotency(
                (int) $cached['teacher_id'],
                (int) $cached['teacher_school_id'],
            );
        }

        $error = $this->guard->rejectionCode(
            $command->teacherId,
            $command->sourceSchoolId,
            $command->targetSchoolId,
            $command->academicYearId,
        );
        if ($error !== null) {
            return AssignTeacherSchoolResult::failure([$error]);
        }

        $at = (new \DateTimeImmutable)->format('Y-m-d H:i:s');
        $teacherSchoolId = $this->unitOfWork->transaction(function () use ($command, $key, $at): int {
            $teacherSchoolId = $this->teachers->assignSchool(
                $command->teacherId,
                $command->targetSchoolId,
                $command->academicYearId,
                false,
                $at,
            );
            $this->outbox->stage(new TeacherSchoolAssigned(
                $command->teacherId,
                $command->sourceSchoolId,
                $command->targetSchoolId,
                $command->academicYearId,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, [
                'teacher_id' => $command->teacherId,
                'teacher_school_id' => $teacherSchoolId,
            ]);

            return $teacherSchoolId;
        });

        return AssignTeacherSchoolResult::success($command->teacherId, $teacherSchoolId);
    }
}
