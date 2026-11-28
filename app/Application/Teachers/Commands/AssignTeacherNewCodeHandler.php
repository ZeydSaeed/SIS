<?php

namespace App\Application\Teachers\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Teachers\Results\AssignTeacherNewCodeResult;
use App\Domain\Teachers\Repositories\TeacherRepositoryInterface;
use App\Domain\Teachers\Services\TeacherCodeGenerator;
use App\Domain\Teachers\Support\TeacherIdempotencyGuard;

/**
 * «تعيين رقم جديد»: draws a code no teacher has, then changes it through ChangeTeacherEmployeeCodeHandler
 * (same guard, outbox event and idempotency — a retried request returns the code already set).
 */
final class AssignTeacherNewCodeHandler implements CommandHandler
{
    /** A clash is about one in 2.8 × 10^10 per pair; a few draws always suffice. */
    private const MAX_DRAWS = 20;

    public function __construct(
        private readonly TeacherRepositoryInterface $teachers,
        private readonly TeacherCodeGenerator $codes,
        private readonly ChangeTeacherEmployeeCodeHandler $change,
    ) {}

    public function handle(Command $command): AssignTeacherNewCodeResult
    {
        assert($command instanceof AssignTeacherNewCodeCommand);
        $key = TeacherIdempotencyGuard::requireKey($command->idempotencyKey);

        if (! $this->teachers->belongsToSchool($command->teacherId, $command->schoolId)) {
            return AssignTeacherNewCodeResult::failure(['teachers.not_found']);
        }

        for ($draw = 0; $draw < self::MAX_DRAWS; $draw++) {
            $code = $this->codes->generate();
            if (! $this->teachers->employeeCodeExists($code)) {
                return AssignTeacherNewCodeResult::fromChange($this->change->handle(new ChangeTeacherEmployeeCodeCommand(
                    schoolId: $command->schoolId,
                    teacherId: $command->teacherId,
                    employeeCode: $code,
                    idempotencyKey: $key,
                )));
            }
        }

        return AssignTeacherNewCodeResult::failure(['teachers.employee_code_taken']);
    }
}
