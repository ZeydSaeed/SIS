<?php

namespace App\Application\Attendance\Support;

use App\Domain\Attendance\Data\SheetMark;
use App\Domain\Attendance\Exceptions\AttendanceRuleViolation;
use App\Domain\Attendance\Repositories\AttendanceSheetRepositoryInterface;
use App\Domain\Attendance\Repositories\AttendanceWriteRepositoryInterface;
use App\Domain\Attendance\Services\SheetMarksGuard;
use App\Domain\Attendance\Services\SheetTally;
use App\Domain\Attendance\ValueObjects\AttendanceRecordStatus;
use App\Domain\Attendance\ValueObjects\EvidenceMethod;

/** Writes a sheet's marks against its roster and returns the resulting tally. */
final class SheetMarkWriter
{
    public function __construct(
        private readonly AttendanceSheetRepositoryInterface $sheets,
        private readonly AttendanceWriteRepositoryInterface $attendance,
    ) {}

    /**
     * @param  list<SheetMark>  $marks
     * @param  bool  $presentRest  submit: only the exceptions need marking — every student left unmarked is recorded present
     * @return array{counts: array<string, int>, normalized: int}
     */
    public function write(SheetContext $context, array $marks, bool $presentRest = false): array
    {
        $sheet = $context->sheet;
        $roster = $this->sheets->roster($sheet->schoolId, $sheet->academicYearId, $sheet->sectionId, $sheet->groupId, $sheet->sessionDate);
        if ($roster === []) {
            throw AttendanceRuleViolation::emptyRoster();
        }

        $normalized = SheetMarksGuard::normalize($marks, $roster, $context->policy, $context->actor->isReviewer);
        if ($normalized['marks'] !== []) {
            $via = $context->actorIsTeacher() ? EvidenceMethod::TeacherManual : EvidenceMethod::Administrative;
            $this->sheets->saveMarks($sheet, $normalized['marks'], $context->actor->userId, $via->value);
            $this->attendance->refreshDailySectionSummary($sheet->sectionId, $sheet->schoolId, $sheet->academicYearId, $sheet->sessionDate);
        }

        $statuses = $this->sheets->statuses($sheet->id, $sheet->academicYearId);
        if ($presentRest) {
            $rest = [];
            foreach ($roster as $entry) {
                if (($statuses[$entry->enrollmentId] ?? 0) === 0) {
                    $rest[] = new SheetMark($entry->enrollmentId, AttendanceRecordStatus::Present->value);
                }
            }
            if ($rest !== []) {
                $via = $context->actorIsTeacher() ? EvidenceMethod::TeacherManual : EvidenceMethod::Administrative;
                $restMarks = SheetMarksGuard::normalize($rest, $roster, $context->policy, $context->actor->isReviewer);
                $this->sheets->saveMarks($sheet, $restMarks['marks'], $context->actor->userId, $via->value);
                $this->attendance->refreshDailySectionSummary($sheet->sectionId, $sheet->schoolId, $sheet->academicYearId, $sheet->sessionDate);
                $statuses = $this->sheets->statuses($sheet->id, $sheet->academicYearId);
            }
        }

        return [
            'counts' => SheetTally::of($roster, $statuses),
            'normalized' => $normalized['normalized'],
        ];
    }
}
