<?php

namespace App\Domain\Timetable\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class ScheduleSlotConflictException extends SisDomainException
{
    public static function forSection(): self
    {
        return new self(
            'Section already has an active schedule in this day/period.',
            'timetable.section_slot_conflict',
        );
    }

    public static function forTeacher(): self
    {
        return new self(
            'Teacher already has an active schedule in this day/period.',
            'timetable.teacher_slot_conflict',
        );
    }

    public static function forRoom(): self
    {
        return new self(
            'Room already has an active schedule in this day/period.',
            'timetable.room_slot_conflict',
        );
    }

    public static function forWorkshop(): self
    {
        return new self(
            'Workshop already has an active schedule in this day/period.',
            'timetable.workshop_slot_conflict',
        );
    }

    /** The teacher teaches in another school at an overlapping time. */
    public static function forTeacherElsewhere(): self
    {
        return new self(
            'Teacher has a lesson in another school at this time.',
            'timetable.teacher_busy_other_school',
        );
    }

    /** The slot is marked «غير متاح» for the teacher / room / workshop / section. */
    public static function unavailable(string $target): self
    {
        return new self(
            'The slot is marked unavailable for the '.$target.'.',
            'timetable.'.$target.'_unavailable_slot',
        );
    }

    public static function fromDatabase(): self
    {
        return new self(
            'Active schedule slot conflict.',
            'timetable.schedule_slot_conflict',
        );
    }
}
