<?php

namespace App\Domain\Curriculum\Services;

use App\Domain\Curriculum\Repositories\SubjectRepositoryInterface;
use App\Domain\Curriculum\ValueObjects\SubjectType;

final class UpdateSubjectGuard
{
    public function __construct(
        private readonly SubjectRepositoryInterface $subjects,
    ) {}

    /**
     * @param  array{name?: string, name_en?: ?string, subject_type?: int, credit_hours?: ?int, max_grade?: int, pass_grade?: int}  $fields
     */
    public function rejectionCode(int $subjectId, array $fields): ?string
    {
        $current = $this->subjects->findActive($subjectId);
        if ($current === null) {
            return 'curriculum.subject_not_found';
        }
        if ($fields === []) {
            return 'curriculum.subject_update_empty';
        }

        $name = $fields['name'] ?? $current->name;
        $type = $fields['subject_type'] ?? $current->subjectType;
        $credit = array_key_exists('credit_hours', $fields) ? $fields['credit_hours'] : $current->creditHours;
        $max = $fields['max_grade'] ?? $current->maxGrade;
        $pass = $fields['pass_grade'] ?? $current->passGrade;

        if (trim((string) $name) === '') {
            return 'curriculum.subject_name_invalid';
        }
        if (SubjectType::tryFrom($type) === null) {
            return 'curriculum.subject_type_invalid';
        }
        if ($credit !== null && ($credit < 0 || $credit > 40)) {
            return 'curriculum.subject_credit_hours_invalid';
        }
        if ($max < 1 || $pass < 0 || $pass > $max) {
            return 'curriculum.subject_grades_invalid';
        }

        return null;
    }
}
