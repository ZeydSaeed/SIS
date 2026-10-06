<?php

namespace App\Application\Admission\Commands;

use App\Application\Admission\Results\ConvertApplicationToStudentResult;
use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Student\Support\StudentNameFormatter;
use App\Domain\Admission\Events\ApplicationConvertedToStudent;
use App\Domain\Admission\Exceptions\ApplicationNotConvertibleException;
use App\Domain\Admission\Exceptions\ApplicationNotFoundException;
use App\Domain\Admission\Repositories\AdmissionRepositoryInterface;
use App\Domain\Admission\ValueObjects\ApplicationStatus;
use App\Domain\Student\Data\CreateStudentData;
use App\Domain\Student\Events\StudentRegistered;
use App\Domain\Student\Exceptions\DuplicateNationalIdException;
use App\Domain\Student\Repositories\StudentDocumentRepositoryInterface;
use App\Domain\Student\Repositories\StudentRepositoryInterface;

final class ConvertApplicationToStudentHandler implements CommandHandler
{
    private const COMMAND_NAME = 'ConvertApplicationToStudent';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly AdmissionRepositoryInterface $admission,
        private readonly StudentRepositoryInterface $students,
        private readonly StudentDocumentRepositoryInterface $studentDocuments,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): ConvertApplicationToStudentResult
    {
        assert($command instanceof ConvertApplicationToStudentCommand);

        if ($command->idempotencyKey !== null) {
            $cached = $this->idempotency->find($command->idempotencyKey, self::COMMAND_NAME);
            if ($cached !== null) {
                return ConvertApplicationToStudentResult::fromIdempotency(
                    (int) $cached['application_id'],
                    (int) $cached['student_id'],
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

        $application = $this->admission->findApplicationForSchool($command->applicationId, $command->schoolId);
        if ($application === null) {
            throw ApplicationNotFoundException::forId($command->applicationId);
        }

        $status = ApplicationStatus::from($application['status']);
        if ($status === ApplicationStatus::Converted && isset($application['student_id']) && $application['student_id'] !== null) {
            return $this->finish($command, $application, (int) $application['student_id']);
        }

        if (! $status->canConvertToStudent()) {
            throw ApplicationNotConvertibleException::forStatus($application['status']);
        }

        $nationalId = $application['national_id'] !== null && $application['national_id'] !== ''
            ? (string) $application['national_id']
            : null;

        if ($nationalId !== null) {
            $existingStudentId = $this->students->findIdByNationalIdForSchool($nationalId, $command->schoolId);
            if ($existingStudentId !== null) {
                throw DuplicateNationalIdException::forNationalId($nationalId);
            }

            if ($this->students->existsByNationalId($nationalId)) {
                throw DuplicateNationalIdException::forNationalIdInOtherSchool($nationalId);
            }
        }

        $fullName = StudentNameFormatter::fullName(
            firstName: $application['first_name'],
            lastName: $application['last_name'],
            fatherName: $application['father_name'] ?? null,
            grandfatherName: $application['grandfather_name'] ?? null,
            greatGrandfatherName: $application['great_grandfather_name'] ?? null,
        );
        $birthDate = $this->normalizeDate((string) $application['birth_date']);
        $admittedAcademicYearId = isset($application['academic_year_id'])
            ? (int) $application['academic_year_id']
            : null;

        $studentId = $this->unitOfWork->transaction(function () use (
            $command,
            $application,
            $fullName,
            $birthDate,
            $admittedAcademicYearId,
        ): int {
            $studentCode = $this->students->generateStudentCode();
            $intendedGrade = isset($application['intended_grade_name']) && is_string($application['intended_grade_name'])
                ? trim($application['intended_grade_name'])
                : '';
            $departmentName = isset($application['department_name']) && is_string($application['department_name'])
                ? trim($application['department_name'])
                : '';
            $requestKind = isset($application['request_kind']) ? (int) $application['request_kind'] : 2;

            $id = $this->students->saveNew(new CreateStudentData(
                studentCode: $studentCode,
                firstName: $application['first_name'],
                middleName: null,
                fatherName: $application['father_name'] ?? null,
                grandfatherName: $application['grandfather_name'] ?? null,
                greatGrandfatherName: $application['great_grandfather_name'] ?? null,
                motherName: $application['mother_name'] ?? null,
                maternalFatherName: $application['maternal_father_name'] ?? null,
                maternalGrandfatherName: $application['maternal_grandfather_name'] ?? null,
                lastName: $application['last_name'],
                fullName: $fullName,
                gender: (int) $application['gender'],
                birthDate: $birthDate,
                nationalId: $application['national_id'],
                birthPlace: $application['birth_place'] ?? null,
                governorate: $application['governorate'] ?? null,
                neighborhood: $application['neighborhood'] ?? null,
                admittedClassName: $intendedGrade !== '' ? $intendedGrade : null,
                notes: $application['notes'] ?? null,
                mobile: $application['student_mobile'] ?? null,
                guardianMobile: $application['guardian_mobile'] ?? null,
                schoolName: $application['school_name'] ?? null,
                branchId: isset($application['branch_id']) ? (int) $application['branch_id'] : null,
                departmentName: $departmentName !== '' ? $departmentName : null,
                previousSchoolName: $application['previous_school_name'] ?? null,
                fatherOccupation: $application['father_occupation'] ?? null,
                motherOccupation: $application['mother_occupation'] ?? null,
                administrativeUnit: isset($application['administrative_unit']) ? (int) $application['administrative_unit'] : null,
                graduationYear: isset($application['graduation_year']) ? (int) $application['graduation_year'] : null,
                previousGpa: isset($application['previous_gpa']) ? (float) $application['previous_gpa'] : null,
                previousStudyTrack: isset($application['previous_study_track']) ? (int) $application['previous_study_track'] : null,
                mathematicsGrade: isset($application['mathematics_grade']) ? (float) $application['mathematics_grade'] : null,
                physicsGrade: isset($application['physics_grade']) ? (float) $application['physics_grade'] : null,
                requestKind: $requestKind,
                schoolId: $command->schoolId,
                admittedAcademicYearId: $admittedAcademicYearId,
            ));

            $this->copyApplicationDocumentsToStudent(
                $command->applicationId,
                $command->schoolId,
                $id,
            );

            $this->admission->markConverted($command->applicationId, $id, $command->reviewedBy);

            $this->outbox->stage(new StudentRegistered(
                studentId: $id,
                studentCode: $studentCode,
                fullName: $fullName,
                occurredAt: new \DateTimeImmutable,
            ));

            $this->outbox->stage(new ApplicationConvertedToStudent(
                applicationId: $command->applicationId,
                studentId: $id,
                schoolId: $command->schoolId,
                applicationNumber: $application['application_number'],
                occurredAt: new \DateTimeImmutable,
            ));

            return $id;
        });

        return $this->finish($command, $application, $studentId);
    }

    /**
     * @param  array<string, mixed>  $application
     */
    private function finish(
        ConvertApplicationToStudentCommand $command,
        array $application,
        int $studentId,
    ): ConvertApplicationToStudentResult {
        if ($command->idempotencyKey !== null) {
            $this->idempotency->store($command->idempotencyKey, self::COMMAND_NAME, [
                'application_id' => $command->applicationId,
                'student_id' => $studentId,
                'academic_year_id' => $application['academic_year_id'] ?? null,
                'branch_id' => $application['branch_id'] ?? null,
                'specialization_id' => $application['specialization_id'] ?? null,
                'grade_level_id' => $application['grade_level_id'] ?? null,
                'department_name' => $application['department_name'] ?? null,
            ]);
        }

        return ConvertApplicationToStudentResult::success(
            $command->applicationId,
            $studentId,
            isset($application['academic_year_id']) ? (int) $application['academic_year_id'] : null,
            isset($application['branch_id']) ? (int) $application['branch_id'] : null,
            isset($application['specialization_id']) ? (int) $application['specialization_id'] : null,
            isset($application['grade_level_id']) ? (int) $application['grade_level_id'] : null,
            isset($application['department_name']) && is_string($application['department_name'])
                ? $application['department_name']
                : null,
        );
    }

    private function normalizeDate(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return $trimmed;
        }

        try {
            return (new \DateTimeImmutable($trimmed))->format('Y-m-d');
        } catch (\Exception) {
            return substr($trimmed, 0, 10);
        }
    }

    private function copyApplicationDocumentsToStudent(int $applicationId, int $schoolId, int $studentId): void
    {
        $docs = $this->admission->listDocumentsForApplication($applicationId);
        if ($docs === []) {
            return;
        }

        $existingTypes = [];
        foreach ($this->studentDocuments->listActiveForStudent($schoolId, $studentId) as $existing) {
            $existingTypes[$existing->documentType] = true;
        }

        $now = (new \DateTimeImmutable)->format(\DateTimeInterface::ATOM);
        foreach ($docs as $doc) {
            $type = $doc['document_type'];
            if (isset($existingTypes[$type])) {
                continue;
            }

            $fileName = $doc['file_name'];
            $this->studentDocuments->create(
                $schoolId,
                $studentId,
                $type,
                $doc['storage_key'],
                $fileName,
                $this->guessMimeType($fileName),
                0,
                $doc['file_hash'],
                null,
                $now,
            );
            $existingTypes[$type] = true;
        }
    }

    private function guessMimeType(string $fileName): string
    {
        $lower = strtolower($fileName);
        if (str_ends_with($lower, '.png')) {
            return 'image/png';
        }
        if (str_ends_with($lower, '.webp')) {
            return 'image/webp';
        }
        if (str_ends_with($lower, '.pdf')) {
            return 'application/pdf';
        }

        return 'image/jpeg';
    }
}
