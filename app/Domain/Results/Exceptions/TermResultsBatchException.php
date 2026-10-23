<?php

namespace App\Domain\Results\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

/** Whole-request refusals of the term-results sheet (no student is processed). */
final class TermResultsBatchException extends SisDomainException
{
    public static function termNotInYear(int $termId): self
    {
        return new self("Term {$termId} is not a term of the academic year.", 'results.batch.term_invalid');
    }

    public static function classNotInSchoolYear(int $classId): self
    {
        return new self("Class {$classId} is not a class of this school in the academic year.", 'results.batch.class_invalid');
    }

    public static function sectionNotInClass(int $sectionId): self
    {
        return new self("Section {$sectionId} does not belong to the chosen class.", 'results.batch.section_invalid');
    }
}
