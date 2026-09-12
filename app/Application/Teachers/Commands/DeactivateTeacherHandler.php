<?php

namespace App\Application\Teachers\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Teachers\Results\DeactivateTeacherResult;
use App\Domain\Teachers\Events\TeacherDeactivated;
use App\Domain\Teachers\Repositories\TeacherRepositoryInterface;
use App\Domain\Teachers\Support\TeacherIdempotencyGuard;
use App\Domain\Teachers\ValueObjects\TeacherStatus;

final class DeactivateTeacherHandler implements CommandHandler
{
    private const COMMAND_NAME = 'DeactivateTeacher';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly TeacherRepositoryInterface $teachers,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): DeactivateTeacherResult
    {
        assert($command instanceof DeactivateTeacherCommand);
        $key = TeacherIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return DeactivateTeacherResult::fromIdempotency((int) $cached['teacher_id']);
        }

        if (! $this->teachers->belongsToSchool($command->teacherId, $command->schoolId)) {
            return DeactivateTeacherResult::failure(['teachers.not_found']);
        }

        $at = (new \DateTimeImmutable)->format('Y-m-d H:i:s');
        $this->unitOfWork->transaction(function () use ($command, $key, $at): void {
            $this->teachers->setStatus($command->teacherId, TeacherStatus::Inactive, $at);
            $this->outbox->stage(new TeacherDeactivated(
                $command->teacherId,
                $command->schoolId,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, ['teacher_id' => $command->teacherId]);
        });

        return DeactivateTeacherResult::success($command->teacherId);
    }
}
