<?php

namespace App\Application\Timetable\DTOs;

/** A computed answer for the page: a run's review, a version comparison, move or substitute suggestions. */
final readonly class TimetableInsightDTO
{
    /** @param  array<string, mixed>  $data */
    public function __construct(
        public array $data,
    ) {}
}
