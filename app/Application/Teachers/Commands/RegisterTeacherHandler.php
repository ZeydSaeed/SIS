<?php

namespace App\Application\Teachers\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Teachers\Results\RegisterTeacherResult;
use App\Domain\Teachers\Events\TeacherRegistered;
use App\Domain\Teachers\Repositories\TeacherRepositoryInterface;
use App\Domain\Teachers\Support\TeacherIdempotencyGuard;
use App\Domain\Teachers\ValueObjects\TeacherStatus;

final class RegisterTeacherHandler implements CommandHandler
{
    private const COMMAND_NAME = 'RegisterTeacher';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly TeacherRepositoryInterface $teachers,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): RegisterTeacherResult
    {
        assert($command instanceof RegisterTeacherCommand);
        $key = TeacherIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return RegisterTeacherResult::fromIdempotency((int) $cached['teacher_id']);
        }

        $code = trim($command->employeeCode);
        if ($code === '') {
            return RegisterTeacherResult::failure(['teachers.employee_code_required']);
        }
        if ($this->teachers->employeeCodeExists($code)) {
            return RegisterTeacherResult::failure(['teachers.employee_code_taken']);
        }

        $first = trim($command->firstName);
        $last = trim($command->lastName);
        if ($first === '' || $last === '') {
            return RegisterTeacherResult::failure(['teachers.name_required']);
        }

        $at = (new \DateTimeImmutable)->format('Y-m-d H:i:s');
        $fullName = trim($first.' '.$last);

        $teacherId = $this->unitOfWork->transaction(function () use ($command, $key, $code, $first, $last, $fullName, $at): int {
            $teacherId = $this->teachers->createTeacher(
                $command->userId,
                $code,
                $command->nationalId !== null && trim($command->nationalId) !== '' ? trim($command->nationalId) : null,
                $first,
                $last,
                $fullName,
                $command->specializationField !== null && trim($command->specializationField) !== ''
                    ? trim($command->specializationField)
                    : null,
                $command->hireDate,
                TeacherStatus::Active,
                $at,
            );
            $this->teachers->assignSchool(
                $teacherId,
                $command->schoolId,
                $command->academicYearId,
                true,
                $at,
            );
            $this->outbox->stage(new TeacherRegistered(
                $teacherId,
                $command->schoolId,
                $command->academicYearId,
                $code,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, ['teacher_id' => $teacherId]);

            return $teacherId;
        });

        return RegisterTeacherResult::success($teacherId);
    }
}
