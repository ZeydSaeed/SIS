<?php

namespace Tests\Unit\Admission;

use App\Application\Admission\Commands\RegisterStudentViaAdmissionCommand;
use App\Application\Admission\Commands\RegisterStudentViaAdmissionHandler;
use App\Application\Admission\Support\PersistConvertedStudentViaAdmission;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Domain\Admission\Data\CreateApplicationDraftData;
use App\Domain\Admission\Repositories\AdmissionRepositoryInterface;
use App\Domain\Admission\Services\CreateApplicationDraftGuard;
use App\Domain\Admission\ValueObjects\ApplicationPeriodStatus;
use App\Domain\Student\Data\CreateStudentData;
use App\Domain\Student\Repositories\StudentRepositoryInterface;
use PHPUnit\Framework\TestCase;

class RegisterStudentViaAdmissionHandlerTest extends TestCase
{
    public function test_creates_application_student_and_marks_converted(): void
    {
        $admission = $this->createMock(AdmissionRepositoryInterface::class);
        $admission->method('findPeriodForSchool')->willReturn([
            'id' => 3,
            'school_id' => 9,
            'academic_year_id' => 12,
            'name' => 'فترة 1',
            'status' => ApplicationPeriodStatus::Active->value,
            'start_date' => '2020-01-01',
            'end_date' => '2099-12-31',
            'max_applications' => null,
        ]);
        $admission->method('countApplicationsInPeriod')->willReturn(0);
        $admission->method('generateApplicationNumber')->willReturn('APP-9-12-0001');
        $admission->expects($this->once())
            ->method('createApplication')
            ->with($this->callback(fn (CreateApplicationDraftData $data): bool => $data->firstName === 'أحمد'
                && $data->lastName === 'كاظم'
                && $data->intendedGradeName === 'الرابع'))
            ->willReturn(55);
        $admission->method('findApplicationForSchool')->willReturn([
            'id' => 55,
            'application_period_id' => 3,
            'application_number' => 'APP-9-12-0001',
            'first_name' => 'أحمد',
            'father_name' => 'علي',
            'grandfather_name' => 'حسن',
            'great_grandfather_name' => 'محمود',
            'last_name' => 'كاظم',
            'mother_name' => 'فاطمة',
            'maternal_father_name' => 'يوسف',
            'maternal_grandfather_name' => 'سالم',
            'national_id' => null,
            'birth_date' => '2012-03-15',
            'birth_place' => 'بغداد',
            'gender' => 1,
            'branch_id' => 2,
            'grade_level_id' => 4,
            'intended_grade_name' => 'الرابع',
            'department_name' => 'صناعي',
            'specialization_id' => null,
            'specialization_name' => null,
            'governorate' => 'بغداد',
            'neighborhood' => 'الكرادة',
            'school_name' => 'Demo School',
            'status' => 1,
            'notes' => null,
            'student_id' => null,
            'school_id' => 9,
            'academic_year_id' => 12,
        ]);
        $admission->expects($this->once())->method('markConverted')->with(55, 91, 7);

        $students = $this->createMock(StudentRepositoryInterface::class);
        $students->method('existsByNationalId')->willReturn(false);
        $students->method('generateStudentCode')->willReturn('STU-000091');
        $students->expects($this->once())
            ->method('saveNew')
            ->with($this->callback(fn (CreateStudentData $data): bool => $data->firstName === 'أحمد'
                && $data->lastName === 'كاظم'
                && $data->admittedClassName === 'الرابع'
                && $data->schoolName === 'Demo School'
                && $data->admittedAcademicYearId === 12
                && $data->branchId === 2))
            ->willReturn(91);

        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork->method('transaction')->willReturnCallback(fn (callable $callback) => $callback());

        $handler = new RegisterStudentViaAdmissionHandler(
            $unitOfWork,
            $students,
            $this->createMock(IdempotencyStore::class),
            new CreateApplicationDraftGuard($admission),
            new PersistConvertedStudentViaAdmission(
                $admission,
                $students,
                $this->createMock(OutboxRepository::class),
            ),
        );

        $result = $handler->handle(new RegisterStudentViaAdmissionCommand(
            schoolId: 9,
            applicationPeriodId: 3,
            firstName: 'أحمد',
            fatherName: 'علي',
            grandfatherName: 'حسن',
            greatGrandfatherName: 'محمود',
            lastName: 'كاظم',
            motherName: 'فاطمة',
            maternalFatherName: 'يوسف',
            maternalGrandfatherName: 'سالم',
            birthDate: '2012-03-15',
            birthPlace: 'بغداد',
            gender: 1,
            targetSchoolId: 9,
            intendedGradeName: 'الرابع',
            gradeLevelId: 4,
            branchId: 2,
            departmentName: 'صناعي',
            governorate: 'بغداد',
            neighborhood: 'الكرادة',
            reviewedBy: 7,
        ));

        $this->assertTrue($result->success);
        $this->assertSame(55, $result->applicationId);
        $this->assertSame(91, $result->studentId);
        $this->assertSame('APP-9-12-0001', $result->applicationNumber);
        $this->assertSame(12, $result->academicYearId);
        $this->assertSame(2, $result->branchId);
        $this->assertSame('صناعي', $result->departmentName);
    }
}
