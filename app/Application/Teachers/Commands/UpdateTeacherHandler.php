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
use App\Application\Teachers\Support\TeacherNameFormatter;
use App\Domain\Teachers\Support\TeacherIdempotencyGuard;
use App\Domain\Teachers\Services\TeacherIdentityGuard;
use App\Domain\Teachers\Services\TeacherTitleGuard;
use App\Domain\Teachers\ValueObjects\TeacherWorkloadLimits;

final class UpdateTeacherHandler implements CommandHandler
{
    private const COMMAND_NAME = 'UpdateTeacher';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly TeacherRepositoryInterface $teachers,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
        private readonly TeacherIdentityGuard $identity,
        private readonly TeacherTitleGuard $title,
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

        $identityError = $this->identity->rejectionCode($command->academicYearId, $command->nationalId, $command->teacherId);
        if ($identityError !== null) {
            return UpdateTeacherResult::failure([$identityError]);
        }

        $first = trim($command->firstName);
        $last = trim($command->lastName);
        if ($first === '' || $last === '') {
            return UpdateTeacherResult::failure(['teachers.name_required']);
        }

        $profileError = $this->title->profileRejection($command->employmentType, $command->updateTitle, $command->academicTitleId, $command->abbreviation);
        if ($profileError !== null) {
            return UpdateTeacherResult::failure([$profileError]);
        }

        $workloadError = TeacherWorkloadLimits::errorForUpdate(
            $command->updateWorkload,
            $command->academicYearId,
            $command->weeklyLessonsMin,
            $command->weeklyLessonsMax,
            $command->dailyLessonsMax,
        );
        if ($workloadError !== null) {
            return UpdateTeacherResult::failure([$workloadError]);
        }

        $at = (new \DateTimeImmutable)->format('Y-m-d H:i:s');
        $father = TeacherNameFormatter::blankToNull($command->fatherName);
        $grandfather = TeacherNameFormatter::blankToNull($command->grandfatherName);
        $this->unitOfWork->transaction(function () use ($command, $key, $first, $last, $father, $grandfather, $at): void {
            $fields = [
                'first_name' => $first,
                'father_name' => $father,
                'grandfather_name' => $grandfather,
                'last_name' => $last,
                'full_name' => TeacherNameFormatter::fullName($first, $father, $grandfather, $last),
                'national_id' => TeacherNameFormatter::blankToNull($command->nationalId),
                'specialization_field' => TeacherNameFormatter::blankToNull($command->specializationField),
                'hire_date' => $command->hireDate,
                'updated_at' => $at,
            ];
            if ($command->userId !== null) {
                $fields['user_id'] = $command->userId;
            }
            $fields += TeacherTitleGuard::columns($command->updateTitle, $command->academicTitleId, $command->abbreviation);
            $this->teachers->updateTeacher($command->teacherId, $fields);
            if ($command->academicYearId !== null) {
                $this->teachers->setEmploymentType(
                    $command->teacherId,
                    $command->schoolId,
                    $command->academicYearId,
                    $command->employmentType,
                );
            }
            if ($command->updateWorkload) {
                $this->teachers->setWorkloadLimits(
                    $command->teacherId,
                    $command->schoolId,
                    (int) $command->academicYearId,
                    $command->weeklyLessonsMin,
                    $command->weeklyLessonsMax,
                    $command->dailyLessonsMax,
                );
            }
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
