<?php

namespace App\Application\Results\Support;

use App\Application\Results\Commands\ProcessTermResultsBatchCommand;
use App\Application\Results\Contracts\TermResultSheetReadRepositoryInterface;
use App\Domain\Results\Exceptions\TermResultsBatchException;

/** The term must be of the academic year, the class of the school-year and the section of the class (term and year sheets). */
final class TermResultsBatchTargetValidator
{
    public function __construct(
        private readonly TermResultSheetReadRepositoryInterface $sheet,
    ) {}

    public function assertValid(ProcessTermResultsBatchCommand $command): void
    {
        if (! $this->sheet->termInYear($command->termId, $command->academicYearId)) {
            throw TermResultsBatchException::termNotInYear($command->termId);
        }
        $this->assertClassAndSection($command->schoolId, $command->academicYearId, $command->classId, $command->sectionId);
    }

    public function assertClassAndSection(int $schoolId, int $academicYearId, int $classId, ?int $sectionId): void
    {
        if (! $this->sheet->classInSchoolYear($classId, $schoolId, $academicYearId)) {
            throw TermResultsBatchException::classNotInSchoolYear($classId);
        }
        if ($sectionId !== null && ! $this->sheet->sectionInClass($sectionId, $classId)) {
            throw TermResultsBatchException::sectionNotInClass($sectionId);
        }
    }
}
