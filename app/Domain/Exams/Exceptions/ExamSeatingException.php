<?php

namespace App\Domain\Exams\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

/** Whole-request refusals of seating a class / section into an exam session (nothing is seated). */
final class ExamSeatingException extends SisDomainException
{
    public static function missingIdempotencyKey(): self
    {
        return new self('Seating a class requires an idempotency key.', 'exams.seating.idempotency_key_required');
    }

    public static function sessionNotFound(int $sessionId): self
    {
        return new self("Exam session {$sessionId} does not exist in this exam and school.", 'exams.seating.session_not_found');
    }

    public static function sessionCancelled(int $sessionId): self
    {
        return new self("Exam session {$sessionId} is cancelled.", 'exams.seating.session_cancelled');
    }

    public static function classNotInExamYear(int $classId): self
    {
        return new self("Class {$classId} is not a class of this school in the exam's academic year.", 'exams.seating.class_invalid');
    }

    public static function sectionNotInClass(int $sectionId): self
    {
        return new self("Section {$sectionId} does not belong to the chosen class.", 'exams.seating.section_invalid');
    }
}
