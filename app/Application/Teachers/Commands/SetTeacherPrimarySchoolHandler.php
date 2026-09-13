<?php

namespace App\Application\Teachers\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Teachers\Results\SetTeacherPrimarySchoolResult;
use App\Domain\Teachers\Events\TeacherPrimarySchoolChanged;
use App\Domain\Teachers\Repositories\TeacherRepositoryInterface;
use App\Domain\Teachers\Services\SetTeacherPrimarySchoolGuard;
use App\Domain\Teachers\Support\TeacherIdempotencyGuard;

final class SetTeacherPrimarySchoolHandler implements CommandHandler
{
    private const COMMAND_NAME = 'SetTeacherPrimarySchool';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly TeacherRepositoryInterface $teachers,
        private readonly SetTeacherPrimarySchoolGuard $guard,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): SetTeacherPrimarySchoolResult
    {
        assert($command instanceof SetTeacherPrimarySchoolCommand);
        $key = TeacherIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return SetTeacherPrimarySchoolResult::fromIdempotency((int) $cached['teacher_id']);
        }

        $error = $this->guard->rejectionCode(
            $command->teacherId,
            $command->sourceSchoolId,
            $command->targetSchoolId,
            $command->academicYearId,
        );
        if ($error !== null) {
            return SetTeacherPrimarySchoolResult::failure([$error]);
        }

        $this->unitOfWork->transaction(function () use ($command, $key): void {
            if (! $this->teachers->isPrimaryInSchool(
                $command->teacherId,
                $command->targetSchoolId,
                $command->academicYearId,
            )) {
                $this->teachers->setMembershipPrimary(
                    $command->teacherId,
                    $command->sourceSchoolId,
                    $command->academicYearId,
                    false,
                );
                $this->teachers->setMembershipPrimary(
                    $command->teacherId,
                    $command->targetSchoolId,
                    $command->academicYearId,
                    true,
                );
                $this->outbox->stage(new TeacherPrimarySchoolChanged(
                    $command->teacherId,
                    $command->sourceSchoolId,
                    $command->targetSchoolId,
                    $command->academicYearId,
                    new \DateTimeImmutable,
                ));
            }
            $this->idempotency->store($key, self::COMMAND_NAME, [
                'teacher_id' => $command->teacherId,
            ]);
        });

        return SetTeacherPrimarySchoolResult::success($command->teacherId);
    }
}
