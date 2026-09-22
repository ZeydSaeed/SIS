<?php

namespace Tests\Unit\Admission;

use App\Application\Admission\Commands\ConvertApplicationToStudentCommand;
use App\Application\Admission\Commands\ConvertApplicationToStudentHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Domain\Admission\Repositories\AdmissionRepositoryInterface;
use App\Domain\Admission\ValueObjects\ApplicationStatus;
use App\Domain\Student\Data\CreateStudentData;
use App\Domain\Student\Repositories\StudentRepositoryInterface;
use PHPUnit\Framework\TestCase;

class ConvertApplicationToStudentHandlerTest extends TestCase
{
    public function test_copies_civil_and_academic_admission_fields_onto_the_student(): void
    {
        $admission = $this->createMock(AdmissionRepositoryInterface::class);
        $admission->method('findApplicationForSchool')->willReturn([
            'id' => 44,
            'application_period_id' => 3,
            'application_number' => 'APP-000044',
            'first_name' => 'أحمد',
            'father_name' => 'علي',
            'grandfather_name' => 'حسن',
            'great_grandfather_name' => 'محمود',
            'last_name' => 'كاظم',
            'mother_name' => 'فاطمة',
            'maternal_father_name' => 'يوسف',
            'maternal_grandfather_name' => 'سالم',
            'national_id' => 'NID-44',
            'birth_date' => '2012-03-15',
            'birth_place' => 'بغداد',
            'gender' => 1,
            'grade_level_id' => null,
            'intended_grade_name' => 'الرابع',
            'department_name' => 'صناعي',
            'specialization_id' => null,
            'specialization_name' => 'كهرباء',
            'governorate' => 'بغداد',
            'neighborhood' => 'الكرادة',
            'school_name' => 'Demo Vocational School',
            'status' => ApplicationStatus::Accepted->value,
            'notes' => null,
            'student_id' => null,
            'school_id' => 9,
            'academic_year_id' => 12,
        ]);
        $admission->expects($this->once())->method('markConverted')->with(44, 81, 7);

        $students = $this->createMock(StudentRepositoryInterface::class);
        $students->method('existsByNationalId')->willReturn(false);
        $students->method('generateStudentCode')->willReturn('STU-000081');
        $students->expects($this->once())
            ->method('saveNew')
            ->with($this->callback(fn (CreateStudentData $data): bool => $data->firstName === 'أحمد'
                && $data->fatherName === 'علي'
                && $data->grandfatherName === 'حسن'
                && $data->greatGrandfatherName === 'محمود'
                && $data->lastName === 'كاظم'
                && $data->motherName === 'فاطمة'
                && $data->maternalFatherName === 'يوسف'
                && $data->maternalGrandfatherName === 'سالم'
                && $data->birthPlace === 'بغداد'
                && $data->governorate === 'بغداد'
                && $data->neighborhood === 'الكرادة'
                && $data->schoolName === 'Demo Vocational School'
                && $data->admittedClassName === 'الرابع'
                && $data->departmentName === 'صناعي'
                && $data->specializationName === 'كهرباء'
                && $data->nationalId === 'NID-44'
                && $data->admittedAcademicYearId === 12))
            ->willReturn(81);

        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork->method('transaction')->willReturnCallback(fn (callable $callback) => $callback());

        $handler = new ConvertApplicationToStudentHandler(
            $unitOfWork,
            $admission,
            $students,
            $this->createMock(OutboxRepository::class),
            $this->createMock(IdempotencyStore::class),
        );

        $result = $handler->handle(new ConvertApplicationToStudentCommand(
            schoolId: 9,
            applicationId: 44,
            reviewedBy: 7,
        ));

        $this->assertTrue($result->success);
        $this->assertSame(81, $result->studentId);
        $this->assertSame(12, $result->academicYearId);
        $this->assertSame('صناعي', $result->departmentName);
        $this->assertNull($result->specializationId);
        $this->assertNull($result->gradeLevelId);
        $this->assertNull($result->branchId);
    }
}
