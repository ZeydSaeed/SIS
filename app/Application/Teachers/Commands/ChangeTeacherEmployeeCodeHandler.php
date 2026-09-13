<?php

namespace App\Application\Teachers\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Teachers\Results\ChangeTeacherEmployeeCodeResult;
use App\Domain\Teachers\Events\TeacherEmployeeCodeChanged;
use App\Domain\Teachers\Repositories\TeacherRepositoryInterface;
use App\Domain\Teachers\Services\ChangeTeacherEmployeeCodeGuard;
use App\Domain\Teachers\Support\TeacherIdempotencyGuard;

final class ChangeTeacherEmployeeCodeHandler implements CommandHandler
{
    private const COMMAND_NAME = 'ChangeTeacherEmployeeCode';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly TeacherRepositoryInterface $teachers,
        private readonly ChangeTeacherEmployeeCodeGuard $guard,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): ChangeTeacherEmployeeCodeResult
    {
        assert($command instanceof ChangeTeacherEmployeeCodeCommand);
        $key = TeacherIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return ChangeTeacherEmployeeCodeResult::fromIdempotency(
                (int) $cached['teacher_id'],
                (string) $cached['employee_code'],
            );
        }

        $code = strtoupper(trim($command->employeeCode));
        $error = $this->guard->rejectionCode($command->schoolId, $command->teacherId, $code);
        if ($error !== null) {
            return ChangeTeacherEmployeeCodeResult::failure([$error]);
        }

        $at = (new \DateTimeImmutable)->format('Y-m-d H:i:s');
        $this->unitOfWork->transaction(function () use ($command, $key, $code, $at): void {
            $this->teachers->changeEmployeeCode($command->schoolId, $command->teacherId, $code, $at);
            $this->outbox->stage(new TeacherEmployeeCodeChanged(
                $command->teacherId,
                $command->schoolId,
                $code,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, [
                'teacher_id' => $command->teacherId,
                'employee_code' => $code,
            ]);
        });

        return ChangeTeacherEmployeeCodeResult::success($command->teacherId, $code);
    }
}
