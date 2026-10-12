<?php

namespace App\Application\Attendance\DTOs;

/** Read model of one attendance screen (layer-safe arrays only — no Eloquent models). */
final readonly class AttendanceViewDTO
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public array $data,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
