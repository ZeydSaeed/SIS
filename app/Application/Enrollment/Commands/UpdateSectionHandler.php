<?php

namespace App\Application\Enrollment\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\UnitOfWork;
use App\Application\Enrollment\Results\UpdateSectionResult;
use App\Domain\Enrollment\Contracts\HomeroomTeacherPort;
use App\Domain\Enrollment\Repositories\EnrollmentStructureRepositoryInterface;
use App\Domain\Enrollment\Services\EnrollmentStructureGuard;
use App\Domain\Enrollment\Support\EnrollmentIdempotencyGuard;

final class UpdateSectionHandler implements CommandHandler
{
    private const COMMAND_NAME = 'UpdateSection';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly EnrollmentStructureRepositoryInterface $structure,
        private readonly EnrollmentStructureGuard $guard,
        private readonly HomeroomTeacherPort $homeroomTeachers,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): UpdateSectionResult
    {
        assert($command instanceof UpdateSectionCommand);
        $key = EnrollmentIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return UpdateSectionResult::fromIdempotency((int) $cached['section_id']);
        }

        $section = $this->structure->findSection($command->schoolId, $command->sectionId);
        $class = $section === null ? null : $this->structure->findClass($command->schoolId, $section->classId);
        if ($section === null || $class === null) {
            return UpdateSectionResult::failure('enrollment.section_not_found');
        }

        $error = $this->guard->nameError(
            $command->name,
            $this->structure->sectionNameTaken($section->classId, $command->name, $command->sectionId),
            'enrollment.section',
        ) ?? $this->guard->capacityError(
            $command->capacity,
            $this->structure->countActiveEnrollmentsInSection($command->schoolId, $command->sectionId),
            'enrollment.section',
        );
        // An unchanged homeroom stays valid even if that teacher later left the school.
        if ($error === null
            && $command->homeroomTeacherId !== null
            && $command->homeroomTeacherId !== $section->homeroomTeacherId
            && ! $this->homeroomTeachers->isActiveTeacherOfSchoolYear($command->homeroomTeacherId, $command->schoolId, $class->academicYearId)) {
            $error = 'enrollment.section_homeroom_invalid';
        }
        if ($error !== null) {
            return UpdateSectionResult::failure($error);
        }

        $at = now()->toIso8601String();
        $this->unitOfWork->transaction(function () use ($command, $key, $at): void {
            $this->structure->updateSection(
                $command->schoolId,
                $command->sectionId,
                $command->name,
                $command->capacity,
                $command->homeroomTeacherId,
                $at,
            );
            $this->idempotency->store($key, self::COMMAND_NAME, ['section_id' => $command->sectionId]);
        });

        return UpdateSectionResult::success($command->sectionId);
    }
}
