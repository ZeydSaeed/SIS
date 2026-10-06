<?php

namespace App\Domain\Timetable\Support;

/** The school week and the day limits the builder works with. */
final class SchoolWeek
{
    /** الأحد → الخميس (`day_of_week` 1–5). */
    public const DAYS = [1, 2, 3, 4, 5];

    /** A teacher teaches at most this many lessons a day. */
    public const MAX_TEACHER_LESSONS_PER_DAY = 6;

    /** A section has the same subject at most this many times a day (a practical double counts as two). */
    public const MAX_SUBJECT_LESSONS_PER_DAY = 2;

    /** Two lessons form a practical double across a changeover of at most this many minutes (not the main break). */
    public const MAX_DOUBLE_BREAK_MINUTES = 10;
}
