<?php

namespace App\Domain\Exams\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

/** Exam-type refusals carry stable codes (exams.types.*) that the pages localize. */
final class ExamTypeException extends SisDomainException
{
    public static function invalidCode(): self
    {
        return new self('Exam type code must be 1–20 letters, digits, "-" or "_".', 'exams.types.code_invalid');
    }

    public static function invalidName(): self
    {
        return new self('Exam type name must be 1–100 characters.', 'exams.types.name_invalid');
    }

    public static function invalidWeight(): self
    {
        return new self('Exam type weight must be a whole number from 0 to 100.', 'exams.types.weight_invalid');
    }

    public static function codeTaken(): self
    {
        return new self('Another exam type of the school already uses this code.', 'exams.types.code_taken');
    }

    public static function lockedInUse(): self
    {
        return new self('The code and weight of an exam type used by an exam cannot change (results are calculated from them).', 'exams.types.locked_in_use');
    }

    public static function notFound(): self
    {
        return new self('Exam type does not exist in the current school.', 'exams.types.not_found');
    }
}
