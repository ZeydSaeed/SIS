<?php

namespace App\Application\Student\DTOs;

final readonly class StudentListItemDTO
{
    public function __construct(
        public int $id,
        public string $studentCode,
        public string $fullName,
        public int $status,
        public int $gender,
        public string $birthDate,
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
            'gender' => $this->gender,
            'birth_date' => $this->birthDate,
        ];
    }
}
