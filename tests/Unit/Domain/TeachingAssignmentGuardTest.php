<?php

namespace Tests\Unit\Domain;

use App\Domain\Teachers\Contracts\TeachingPlacementCatalogPort;
use App\Domain\Teachers\Data\TeachingAssignmentData;
use App\Domain\Teachers\Services\TeachingAssignmentGuard;
use PHPUnit\Framework\TestCase;

class TeachingAssignmentGuardTest extends TestCase
{
    public function test_valid_assignment_passes(): void
    {
        $this->assertNull($this->guard()->error($this->data()));
    }

    public function test_each_rule_reports_its_code(): void
    {
        $this->assertSame('teachers.assignment_branch_invalid', $this->guard(branch: false)->error($this->data()));
        $this->assertSame('teachers.assignment_department_invalid', $this->guard(department: false)->error($this->data()));
        $this->assertSame('teachers.assignment_section_needs_class', $this->guard()->error($this->data(classId: null)));
        $this->assertSame('teachers.assignment_class_invalid', $this->guard(class: false)->error($this->data()));
        $this->assertSame('teachers.assignment_section_invalid', $this->guard(section: false)->error($this->data()));
        $this->assertSame('teachers.assignment_subject_not_in_curriculum', $this->guard(curriculum: false)->error($this->data()));
    }

    public function test_optional_places_are_not_checked_when_absent(): void
    {
        $guard = $this->guard(department: false, class: false, section: false);

        $this->assertNull($guard->error($this->data(departmentId: null, classId: null, sectionId: null)));
    }

    private function data(?int $departmentId = 5, ?int $classId = 7, ?int $sectionId = 9): TeachingAssignmentData
    {
        return new TeachingAssignmentData(
            teacherId: 1,
            schoolId: 2,
            academicYearId: 3,
            subjectId: 4,
            branchId: 6,
            departmentId: $departmentId,
            classId: $classId,
            sectionId: $sectionId,
            effectiveFrom: '2026-10-06',
            at: '2026-10-06 10:00:00',
        );
    }

    private function guard(
        bool $branch = true,
        bool $department = true,
        bool $class = true,
        bool $section = true,
        bool $curriculum = true,
    ): TeachingAssignmentGuard {
        $port = $this->createMock(TeachingPlacementCatalogPort::class);
        $port->method('branchInSchool')->willReturn($branch);
        $port->method('departmentInBranch')->willReturn($department);
        $port->method('classInSchoolYear')->willReturn($class);
        $port->method('sectionInClass')->willReturn($section);
        $port->method('subjectInCurriculum')->willReturn($curriculum);

        return new TeachingAssignmentGuard($port);
    }
}
