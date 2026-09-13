<?php

namespace App\Domain\Curriculum\Services;

use App\Domain\Curriculum\Repositories\SubjectRepositoryInterface;
use App\Domain\Curriculum\ValueObjects\SubjectType;

/**
 * Create-subject preconditions — keeps Application handler within ARCH-103.
 */
final class CreateSubjectGuard
{
    public function __construct(
        private readonly SubjectRepositoryInterface $subjects,
    ) {}

    public function rejectionCode(
        string $code,
        string $name,
        int $subjectType,
        ?int $creditHours,
        int $maxGrade,
        int $passGrade,
    ): ?string {
        if (trim($code) === '' || strlen($code) > 20) {
            return 'curriculum.subject_code_invalid';
        }
        if (trim($name) === '') {
            return 'curriculum.subject_name_invalid';
        }
        if (SubjectType::tryFrom($subjectType) === null) {
            return 'curriculum.subject_type_invalid';
        }
        if ($creditHours !== null && ($creditHours < 0 || $creditHours > 40)) {
            return 'curriculum.subject_credit_hours_invalid';
        }
        if ($maxGrade < 1 || $passGrade < 0 || $passGrade > $maxGrade) {
            return 'curriculum.subject_grades_invalid';
        }
        if ($this->subjects->codeExists($code)) {
            return 'curriculum.subject_code_taken';
        }

        return null;
    }
}
