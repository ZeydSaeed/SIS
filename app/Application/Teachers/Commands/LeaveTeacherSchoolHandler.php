<?php

namespace App\Application\Teachers\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Teachers\Results\LeaveTeacherSchoolResult;
use App\Domain\Teachers\Events\TeacherLeftSchool;
use App\Domain\Teachers\Repositories\TeacherRepositoryInterface;
use App\Domain\Teachers\Services\LeaveTeacherSchoolGuard;
use App\Domain\Teachers\Support\TeacherIdempotencyGuard;

final class LeaveTeacherSchoolHandler implements CommandHandler
{
    private const COMMAND_NAME = 'LeaveTeacherSchool';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly TeacherRepositoryInterface $teachers,
        private readonly LeaveTeacherSchoolGuard $guard,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): LeaveTeacherSchoolResult
    {
        assert($command instanceof LeaveTeacherSchoolCommand);
        $key = TeacherIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return LeaveTeacherSchoolResult::fromIdempotency((int) $cached['teacher_id']);
        }

        $error = $this->guard->rejectionCode(
            $command->teacherId,
            $command->schoolId,
            $command->academicYearId,
        );
        if ($error !== null) {
            return LeaveTeacherSchoolResult::failure([$error]);
        }

        $leftAt = new \DateTimeImmutable;

        $this->unitOfWork->transaction(function () use ($command, $key, $leftAt): void {
            $this->teachers->leaveSchool(
                $command->teacherId,
                $command->schoolId,
                $command->academicYearId,
                $leftAt->format(\DateTimeInterface::ATOM),
            );
            $this->outbox->stage(new TeacherLeftSchool(
                $command->teacherId,
                $command->schoolId,
                $command->academicYearId,
                $leftAt,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, [
                'teacher_id' => $command->teacherId,
            ]);
        });

        return LeaveTeacherSchoolResult::success($command->teacherId);
    }
}
