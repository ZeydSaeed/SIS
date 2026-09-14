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

    public static function fromDatabase(): self
    {
        return new self(
            'Active schedule slot conflict.',
            'timetable.schedule_slot_conflict',
        );
    }
}
