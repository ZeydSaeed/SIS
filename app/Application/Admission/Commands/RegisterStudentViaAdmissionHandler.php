<?php

namespace App\Application\Admission\Commands;

use App\Application\Admission\Results\RegisterStudentViaAdmissionResult;
use App\Application\Admission\Support\PersistConvertedStudentViaAdmission;
use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\UnitOfWork;
use App\Application\Student\Support\StudentNameFormatter;
use App\Domain\Admission\Services\CreateApplicationDraftGuard;
use App\Domain\Student\Exceptions\DuplicateNationalIdException;
use App\Domain\Student\Repositories\StudentRepositoryInterface;

/**
 * Admin fast-track: same admission form payload → Converted student row
 * (skips review pipeline). Enrollment continues separately.
 */
final class RegisterStudentViaAdmissionHandler implements CommandHandler
{
    private const COMMAND_NAME = 'RegisterStudentViaAdmission';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly StudentRepositoryInterface $students,
        private readonly IdempotencyStore $idempotency,
        private readonly CreateApplicationDraftGuard $guard,
        private readonly PersistConvertedStudentViaAdmission $persist,
    ) {}

    public function handle(Command $command): RegisterStudentViaAdmissionResult
    {
        assert($command instanceof RegisterStudentViaAdmissionCommand);

        if ($command->idempotencyKey !== null) {
            $cached = $this->idempotency->find($command->idempotencyKey, self::COMMAND_NAME);
            if ($cached !== null) {
                return $this->resultFromCache($cached);
            }
        }

        $period = $this->guard->assertOpenPeriod(
            $command->applicationPeriodId,
            $command->schoolId,
            $command->gender,
        );

        if ($command->nationalId !== null && $this->students->existsByNationalId($command->nationalId)) {
            throw DuplicateNationalIdException::forNationalId($command->nationalId);
        }

        $studentCode = $this->students->generateStudentCode();
        $fullName = StudentNameFormatter::fullName(
            firstName: $command->firstName,
            lastName: $command->lastName,
            fatherName: $command->fatherName,
            grandfatherName: $command->grandfatherName,
            greatGrandfatherName: $command->greatGrandfatherName,
        );

        $result = $this->unitOfWork->transaction(
            fn (): array => $this->persist->execute($command, $period, $studentCode, $fullName),
        );

        if ($command->idempotencyKey !== null) {
            $this->idempotency->store($command->idempotencyKey, self::COMMAND_NAME, [
                'application_id' => $result['application_id'],
                'student_id' => $result['student_id'],
                'application_number' => $result['application_number'],
                'academic_year_id' => $result['academic_year_id'],
                'branch_id' => $command->branchId,
                'specialization_id' => $command->specializationId,
                'grade_level_id' => $command->gradeLevelId,
                'department_name' => $command->departmentName,
            ]);
        }

        return RegisterStudentViaAdmissionResult::success(
            $result['application_id'],
            $result['student_id'],
            $result['application_number'],
            $result['academic_year_id'],
            $command->branchId,
            $command->specializationId,
            $command->gradeLevelId,
            $command->departmentName,
        );
    }

    /**
     * @param  array<string, mixed>  $cached
     */
    private function resultFromCache(array $cached): RegisterStudentViaAdmissionResult
    {
        return RegisterStudentViaAdmissionResult::fromIdempotency(
            (int) $cached['application_id'],
            (int) $cached['student_id'],
            (string) $cached['application_number'],
            isset($cached['academic_year_id']) ? (int) $cached['academic_year_id'] : null,
            isset($cached['branch_id']) ? (int) $cached['branch_id'] : null,
            isset($cached['specialization_id']) ? (int) $cached['specialization_id'] : null,
            isset($cached['grade_level_id']) ? (int) $cached['grade_level_id'] : null,
            isset($cached['department_name']) && is_string($cached['department_name'])
                ? $cached['department_name']
                : null,
        );
    }
}
