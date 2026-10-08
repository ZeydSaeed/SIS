<?php

namespace App\Application\Enrollment\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\UnitOfWork;
use App\Application\Enrollment\Results\UpdateClassResult;
use App\Domain\Enrollment\Repositories\EnrollmentStructureRepositoryInterface;
use App\Domain\Enrollment\Services\EnrollmentStructureGuard;
use App\Domain\Enrollment\Support\EnrollmentIdempotencyGuard;

final class UpdateClassHandler implements CommandHandler
{
    private const COMMAND_NAME = 'UpdateClass';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly EnrollmentStructureRepositoryInterface $structure,
        private readonly EnrollmentStructureGuard $guard,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): UpdateClassResult
    {
        assert($command instanceof UpdateClassCommand);
        $key = EnrollmentIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return UpdateClassResult::fromIdempotency((int) $cached['class_id']);
        }

        $class = $this->structure->findClass($command->schoolId, $command->classId);
        if ($class === null) {
            return UpdateClassResult::failure('enrollment.class_not_found');
        }

        if (! $this->structure->gradeLevelExists($command->gradeLevelId)) {
            return UpdateClassResult::failure('enrollment.class_grade_level_invalid');
        }

        $enrolled = $this->structure->countActiveEnrollmentsInClass($command->schoolId, $command->classId);
        $error = $this->guard->nameError(
            $command->name,
            $this->structure->classNameTaken($command->schoolId, $class->academicYearId, $command->name, $command->classId),
            'enrollment.class',
        )
            ?? $this->guard->capacityError($command->capacity, $enrolled, 'enrollment.class')
            ?? $this->guard->gradeLevelChangeError($class->gradeLevelId, $command->gradeLevelId, $enrolled);
        if ($error !== null) {
            return UpdateClassResult::failure($error);
        }

        $at = now()->toIso8601String();
        $this->unitOfWork->transaction(function () use ($command, $key, $at): void {
            $this->structure->updateClass(
                $command->schoolId,
                $command->classId,
                $command->gradeLevelId,
                $command->name,
                $command->capacity,
                $at,
            );
            $this->idempotency->store($key, self::COMMAND_NAME, ['class_id' => $command->classId]);
        });

        return UpdateClassResult::success($command->classId, $this->guard->capacityWarnings(
            $command->capacity,
            $this->structure->activeSectionCapacitySum($command->schoolId, $command->classId),
        ));
    }
}
