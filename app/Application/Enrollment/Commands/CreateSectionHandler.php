<?php

namespace App\Application\Enrollment\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\UnitOfWork;
use App\Application\Enrollment\Results\CreateSectionResult;
use App\Domain\Enrollment\Contracts\HomeroomTeacherPort;
use App\Domain\Enrollment\Repositories\EnrollmentStructureRepositoryInterface;
use App\Domain\Enrollment\Services\EnrollmentStructureGuard;
use App\Domain\Enrollment\Support\EnrollmentIdempotencyGuard;
use App\Domain\Enrollment\ValueObjects\EnrollmentStructureStatus;

final class CreateSectionHandler implements CommandHandler
{
    private const COMMAND_NAME = 'CreateSection';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly EnrollmentStructureRepositoryInterface $structure,
        private readonly EnrollmentStructureGuard $guard,
        private readonly HomeroomTeacherPort $homeroomTeachers,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): CreateSectionResult
    {
        assert($command instanceof CreateSectionCommand);
        $key = EnrollmentIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return CreateSectionResult::fromIdempotency((int) $cached['section_id']);
        }

        $class = $this->structure->findClass($command->schoolId, $command->classId);
        if ($class === null) {
            return CreateSectionResult::failure('enrollment.class_not_found');
        }

        if ($class->status !== EnrollmentStructureStatus::Active->value) {
            return CreateSectionResult::failure('enrollment.class_inactive');
        }

        $error = $this->guard->nameError(
            $command->name,
            $this->structure->sectionNameTaken($command->classId, $command->name),
            'enrollment.section',
        ) ?? $this->guard->capacityError($command->capacity, 0, 'enrollment.section');
        if ($error === null
            && $command->homeroomTeacherId !== null
            && ! $this->homeroomTeachers->isActiveTeacherOfSchoolYear($command->homeroomTeacherId, $command->schoolId, $class->academicYearId)) {
            $error = 'enrollment.section_homeroom_invalid';
        }
        if ($error !== null) {
            return CreateSectionResult::failure($error);
        }

        $at = now()->toIso8601String();
        $sectionId = $this->unitOfWork->transaction(function () use ($command, $key, $at): int {
            $id = $this->structure->createSection(
                $command->schoolId,
                $command->classId,
                $command->name,
                $command->capacity,
                $command->homeroomTeacherId,
                $at,
            );
            $this->idempotency->store($key, self::COMMAND_NAME, ['section_id' => $id]);

            return $id;
        });

        return CreateSectionResult::success($sectionId, $this->guard->capacityWarnings(
            $class->capacity,
            $this->structure->activeSectionCapacitySum($command->schoolId, $command->classId),
        ));
    }
}
