<?php

namespace App\Application\Enrollment\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\UnitOfWork;
use App\Application\Enrollment\Results\CreateClassResult;
use App\Domain\Enrollment\Repositories\EnrollmentStructureRepositoryInterface;
use App\Domain\Enrollment\Services\EnrollmentStructureGuard;
use App\Domain\Enrollment\Support\EnrollmentIdempotencyGuard;

final class CreateClassHandler implements CommandHandler
{
    private const COMMAND_NAME = 'CreateClass';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly EnrollmentStructureRepositoryInterface $structure,
        private readonly EnrollmentStructureGuard $guard,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): CreateClassResult
    {
        assert($command instanceof CreateClassCommand);
        $key = EnrollmentIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return CreateClassResult::fromIdempotency((int) $cached['class_id']);
        }

        if (! $this->structure->gradeLevelExists($command->gradeLevelId)) {
            return CreateClassResult::failure('enrollment.class_grade_level_invalid');
        }

        $error = $this->guard->nameError(
            $command->name,
            $this->structure->classNameTaken($command->schoolId, $command->academicYearId, $command->name),
            'enrollment.class',
        ) ?? $this->guard->capacityError($command->capacity, 0, 'enrollment.class');
        if ($error !== null) {
            return CreateClassResult::failure($error);
        }

        $at = now()->toIso8601String();
        $classId = $this->unitOfWork->transaction(function () use ($command, $key, $at): int {
            $id = $this->structure->createClass(
                $command->schoolId,
                $command->academicYearId,
                $command->gradeLevelId,
                $command->name,
                $command->capacity,
                $at,
            );
            $this->idempotency->store($key, self::COMMAND_NAME, ['class_id' => $id]);

            return $id;
        });

        return CreateClassResult::success($classId);
    }
}
