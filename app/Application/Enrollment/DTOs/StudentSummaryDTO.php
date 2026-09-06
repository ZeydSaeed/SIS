<?php

namespace App\Application\Enrollment\DTOs;

final readonly class StudentSummaryDTO
{
    public function __construct(
        public int $id,
        public string $studentCode,
        public string $fullName,
        public int $status,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'student_code' => $this->studentCode,
            'full_name' => $this->fullName,
            'status' => $this->status,
        ];
    }
}
