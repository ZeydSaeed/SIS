<?php

namespace App\Application\Teachers\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Teachers\Results\UpdateTeacherResult;
use App\Domain\Teachers\Events\TeacherUpdated;
use App\Domain\Teachers\Repositories\TeacherRepositoryInterface;
use App\Domain\Teachers\Support\TeacherIdempotencyGuard;

final class UpdateTeacherHandler implements CommandHandler
{
    private const COMMAND_NAME = 'UpdateTeacher';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly TeacherRepositoryInterface $teachers,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): UpdateTeacherResult
    {
        assert($command instanceof UpdateTeacherCommand);
        $key = TeacherIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return UpdateTeacherResult::fromIdempotency((int) $cached['teacher_id']);
        }

        if (! $this->teachers->belongsToSchool($command->teacherId, $command->schoolId)) {
            return UpdateTeacherResult::failure(['teachers.not_found']);
        }

        $first = trim($command->firstName);
        $last = trim($command->lastName);
        if ($first === '' || $last === '') {
            return UpdateTeacherResult::failure(['teachers.name_required']);
        }

        $at = (new \DateTimeImmutable)->format('Y-m-d H:i:s');
        $this->unitOfWork->transaction(function () use ($command, $key, $first, $last, $at): void {
            $fields = [
                'first_name' => $first,
                'last_name' => $last,
                'full_name' => trim($first.' '.$last),
                'national_id' => $command->nationalId !== null && trim($command->nationalId) !== ''
                    ? trim($command->nationalId)
                    : null,
                'specialization_field' => $command->specializationField !== null && trim($command->specializationField) !== ''
                    ? trim($command->specializationField)
                    : null,
                'hire_date' => $command->hireDate,
                'updated_at' => $at,
            ];
            if ($command->userId !== null) {
                $fields['user_id'] = $command->userId;
            }
            $this->teachers->updateTeacher($command->teacherId, $fields);
            $this->outbox->stage(new TeacherUpdated(
                $command->teacherId,
                $command->schoolId,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, ['teacher_id' => $command->teacherId]);
        });

        return UpdateTeacherResult::success($command->teacherId);
    }
}
