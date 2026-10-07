<?php

namespace App\Domain\Timetable\ValueObjects;

/**
 * What kind of teaching an activity is. Co-teaching, joined classes and divided groups are not types —
 * they follow from the activity's teachers and targets.
 */
enum ActivityType: int
{
    case Theory = 1;
    case Practical = 2;
    case Laboratory = 3;
    case Workshop = 4;
    case Seminar = 5;
    case Tutorial = 6;
    case ExamSlot = 7;
    case Consultation = 8;
    case Planning = 9;
    case Meeting = 10;
    case Supervision = 11;
    case SubstituteDuty = 12;
    case Special = 13;
    case Elective = 14;
    case Optional = 15;
    case Reserved = 16;
    case Event = 17;

    /** Practice-type activities default to consecutive blocks (doubles). */
    public function isPractice(): bool
    {
        return in_array($this, [self::Practical, self::Laboratory, self::Workshop], true);
    }
}
