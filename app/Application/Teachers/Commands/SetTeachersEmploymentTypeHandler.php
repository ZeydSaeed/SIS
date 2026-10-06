<?php

namespace App\Application\Teachers\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\UnitOfWork;
use App\Application\Teachers\Results\SetTeachersEmploymentTypeResult;
use App\Domain\Teachers\Repositories\TeacherRepositoryInterface;
use App\Domain\Teachers\Support\TeacherIdempotencyGuard;
use App\Domain\Teachers\ValueObjects\TeacherEmploymentType;

/** Bulk نوع التعيين on the school/year memberships — all-or-nothing. */
final class SetTeachersEmploymentTypeHandler implements CommandHandler
{
    private const COMMAND_NAME = 'SetTeachersEmploymentType';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly TeacherRepositoryInterface $teachers,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): SetTeachersEmploymentTypeResult
    {
        assert($command instanceof SetTeachersEmploymentTypeCommand);
        $key = TeacherIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return SetTeachersEmploymentTypeResult::success(array_map('intval', $cached['teacher_ids'] ?? []), true);
        }

        $ids = array_values(array_unique(array_map('intval', $command->teacherIds)));
        if ($ids === [] || count($ids) > ChangeTeachersStatusHandler::MAX_TEACHERS) {
            return SetTeachersEmploymentTypeResult::failure('teachers.selection_invalid');
        }

        if (! in_array($command->employmentType, TeacherEmploymentType::all(), true)) {
            return SetTeachersEmploymentTypeResult::failure('teachers.employment_type_invalid');
        }

        foreach ($ids as $teacherId) {
            if (! $this->teachers->belongsToSchool($teacherId, $command->schoolId, $command->academicYearId)) {
                return SetTeachersEmploymentTypeResult::failure('teachers.not_in_school_year');
            }
        }

        $this->unitOfWork->transaction(function () use ($command, $ids, $key): void {
            foreach ($ids as $teacherId) {
                $this->teachers->setEmploymentType($teacherId, $command->schoolId, $command->academicYearId, $command->employmentType);
            }
            $this->idempotency->store($key, self::COMMAND_NAME, ['teacher_ids' => $ids]);
        });

        return SetTeachersEmploymentTypeResult::success($ids);
    }
}
