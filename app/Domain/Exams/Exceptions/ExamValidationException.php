<?php

namespace App\Domain\Exams\Exceptions;

use DomainException;

final class ExamValidationException extends DomainException
{
    public static function invalidDates(): self
    {
        return new self('Exam end_date must be greater than or equal to start_date.');
    }

    public static function missingAcademicYear(): self
    {
        return new self('Academic year does not exist.');
    }

    public static function missingTerm(): self
    {
        return new self('Term does not exist for the academic year.');
    }

    public static function missingExamType(): self
    {
        return new self('Exam type does not exist.');
    }

    public static function missingIdempotencyKey(): self
    {
        return new self('Idempotency key is required for exam administration commands.');
    }

    public static function emptyUpdate(): self
    {
        return new self('UpdateExam requires at least one allowlisted field or a status transition.');
    }
}
