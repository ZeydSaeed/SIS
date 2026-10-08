<?php

namespace App\Domain\Teachers\Repositories;

/**
 * Read model of the «المعلمون» page (CQRS read side): the school's teachers for a year with
 * their membership (نوع التعيين) and teaching assignments resolved to names.
 */
interface TeacherRosterReadRepositoryInterface
{
    /**
     * @return array{
     *     items: list<array{id:int, employee_code:string, first_name:string, father_name:?string,
     *         grandfather_name:?string, last_name:string, full_name:string, national_id:?string,
     *         specialization_field:?string, hire_date:?string, status:int, is_primary:bool,
     *         employment_type:?int, weekly_lessons_min:?int, weekly_lessons_max:?int, daily_lessons_max:?int}>,
     *     total: int
     * }
     */
    public function roster(int $schoolId, int $academicYearId, int $limit): array;

    /**
     * Active teaching assignments of the school/year.
     *
     * @return list<array{id:int, teacher_id:int, subject_id:int, subject_name:string,
     *     branch_id:int, branch_name:string, department_id:?int, department_name:?string,
     *     class_id:?int, class_name:?string, section_id:?int, section_name:?string, effective_from:string}>
     */
    public function teachingAssignments(int $schoolId, int $academicYearId): array;

    /**
     * Subjects of the active curricula of the school/year — the subject picker of a teaching
     * assignment: general curricula (no department) and per department.
     *
     * @return array{general: list<int>, by_department: array<int, list<int>>}
     */
    public function curriculumSubjectIds(int $schoolId, int $academicYearId): array;
}
