<?php

namespace App\Domain\Exams\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

/** Refusals of the grade review (objection) workflow (EXAMIS B). */
final class GradeReviewException extends SisDomainException
{
    public static function gradeNotReviewable(): self
    {
        return new self('Only the current, non-voided grade can be reviewed.', 'grades.review.grade_not_reviewable');
    }

    public static function alreadyOpen(): self
    {
        return new self('This grade already has an open review.', 'grades.review.already_open');
    }

    public static function notFound(int $id): self
    {
        return new self("Grade review {$id} was not found.", 'grades.review.not_found');
    }

    public static function invalidState(string $status): self
    {
        return new self("The review is {$status} and cannot take this action.", 'grades.review.invalid_state');
    }

    public static function conflictOfInterest(): self
    {
        return new self('The reviewer cannot be the requester or the person who entered the grade.', 'grades.review.conflict_of_interest');
    }

    public static function notAssignee(): self
    {
        return new self('Only the assigned reviewer can decide this review.', 'grades.review.not_assignee');
    }

    public static function noteRequired(): self
    {
        return new self('A written reason / decision note is required.', 'grades.review.note_required');
    }

    public static function newScoreRequired(): self
    {
        return new self('Accepting a review needs the new score (or absence).', 'grades.review.new_score_required');
    }

    public static function decisionInvalid(string $decision): self
    {
        return new self("Unknown decision {$decision}.", 'grades.review.decision_invalid');
    }

    public static function notRequester(): self
    {
        return new self('Only the requester can withdraw this review.', 'grades.review.not_requester');
    }
}
