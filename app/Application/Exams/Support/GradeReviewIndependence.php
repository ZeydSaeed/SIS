<?php

namespace App\Application\Exams\Support;

use App\Domain\Exams\Exceptions\GradeReviewException;
use App\Domain\Exams\Repositories\StudentGradeRepositoryInterface;

/**
 * Four-eyes rule: the reviewer is neither the person who filed the review nor the person who entered the grade under
 * review (a grade that was corrected before keeps its own enterer, so the whole current grade is checked).
 */
final class GradeReviewIndependence
{
    public function __construct(
        private readonly StudentGradeRepositoryInterface $grades,
    ) {}

    /** @param  array{school_id: int, grade_id: int, academic_year_id: int, requested_by: ?int}  $review */
    public function assertIndependent(array $review, int $reviewerId): void
    {
        $grade = $this->grades->findByIdentity($review['grade_id'], $review['academic_year_id'], $review['school_id']);
        if ($reviewerId === $review['requested_by'] || ($grade !== null && $grade->enteredBy === $reviewerId)) {
            throw GradeReviewException::conflictOfInterest();
        }
    }
}
