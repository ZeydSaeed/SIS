<?php

namespace App\Application\Attendance\Support;

use App\Domain\Attendance\Data\AttendanceSheetSnapshot;
use App\Domain\Attendance\ValueObjects\AttendancePolicy;
use App\Domain\Attendance\ValueObjects\LessonWindow;

final readonly class SheetContext
{
    public function __construct(
        public AttendanceSheetSnapshot $sheet,
        public AttendancePolicy $policy,
        public LessonWindow $window,
        public SheetActor $actor,
    ) {}

    /** The actor teaches this lesson (as opposed to a reviewer acting on behalf). */
    public function actorIsTeacher(): bool
    {
        return $this->actor->teacherId !== null
            && ($this->actor->teacherId === $this->sheet->teacherId || $this->actor->teacherId === $this->sheet->coTeacherId);
    }
}
