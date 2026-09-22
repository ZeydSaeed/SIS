<?php

namespace App\Application\Admission\Support;

use App\Application\Admission\Commands\RegisterStudentViaAdmissionCommand;
use App\Application\Contracts\OutboxRepository;
use App\Domain\Admission\Events\ApplicationConvertedToStudent;
use App\Domain\Admission\Repositories\AdmissionRepositoryInterface;
use App\Domain\Student\Events\StudentRegistered;
use App\Domain\Student\Repositories\StudentRepositoryInterface;

/** Persists application + student and marks Converted inside an open UoW transaction. */
final class PersistConvertedStudentViaAdmission
{
    public function __construct(
        private readonly AdmissionRepositoryInterface $admission,
        private readonly StudentRepositoryInterface $students,
        private readonly OutboxRepository $outbox,
    ) {}

    /**
     * @param  array{academic_year_id:int}  $period
     * @return array{application_id:int,student_id:int,application_number:string,academic_year_id:int}
     */
    public function execute(
        RegisterStudentViaAdmissionCommand $command,
        array $period,
        string $studentCode,
        string $fullName,
    ): array {
        $number = $this->admission->generateApplicationNumber(
            $command->schoolId,
            $period['academic_year_id'],
        );

        $applicationId = $this->admission->createApplication(
            RegisterStudentViaAdmissionMapper::toApplicationDraft($command, $number),
        );

        $application = $this->admission->findApplicationForSchool($applicationId, $command->schoolId);
        $schoolName = is_array($application) && is_string($application['school_name'] ?? null)
            ? $application['school_name']
            : null;

        $studentId = $this->students->saveNew(
            RegisterStudentViaAdmissionMapper::toCreateStudentData(
                $command,
                $studentCode,
                $fullName,
                $period['academic_year_id'],
                $schoolName,
            ),
        );

        $this->admission->markConverted($applicationId, $studentId, $command->reviewedBy);

        $this->outbox->stage(new StudentRegistered(
            studentId: $studentId,
            studentCode: $studentCode,
            fullName: $fullName,
            occurredAt: new \DateTimeImmutable,
        ));

        $this->outbox->stage(new ApplicationConvertedToStudent(
            applicationId: $applicationId,
            studentId: $studentId,
            schoolId: $command->schoolId,
            applicationNumber: $number,
            occurredAt: new \DateTimeImmutable,
        ));

        return [
            'application_id' => $applicationId,
            'student_id' => $studentId,
            'application_number' => $number,
            'academic_year_id' => $period['academic_year_id'],
        ];
    }
}
