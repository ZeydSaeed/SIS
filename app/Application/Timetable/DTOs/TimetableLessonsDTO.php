<?php

namespace App\Application\Timetable\DTOs;

/**
 * A list of lessons with where they come from: a version's entries, a student's week, or the effective
 * timetable of a date (published version + that date's substitutions).
 */
final readonly class TimetableLessonsDTO
{
    /**
     * @param  list<array<string, mixed>>  $lessons
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public array $lessons,
        public array $meta = [],
    ) {}
}
