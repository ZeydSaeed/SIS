<?php

namespace App\Application\Results\Support;

use App\Application\Results\Commands\ProcessTermResultsBatchCommand;
use App\Application\Results\Contracts\TermResultSheetReadRepositoryInterface;
use App\Domain\Results\Exceptions\TermResultsBatchException;

/** The term must be of the academic year, the class of the school-year and the section of the class. */
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
        if (! $this->sheet->classInSchoolYear($command->classId, $command->schoolId, $command->academicYearId)) {
            throw TermResultsBatchException::classNotInSchoolYear($command->classId);
        }
        if ($command->sectionId !== null && ! $this->sheet->sectionInClass($command->sectionId, $command->classId)) {
            throw TermResultsBatchException::sectionNotInClass($command->sectionId);
        }
    }
}
