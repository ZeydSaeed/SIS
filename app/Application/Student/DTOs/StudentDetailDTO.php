<?php

namespace App\Application\Student\DTOs;

final readonly class StudentDetailDTO
{
    public function __construct(
        public int $id,
        public ?string $publicId,
        public string $studentCode,
        public ?string $nationalId,
        public string $firstName,
        public ?string $middleName,
        public string $lastName,
        public string $fullName,
        public int $gender,
        public string $birthDate,
        public ?string $birthPlace,
        public ?string $nationality,
        public int $status,
        public string $createdAt,
        public string $updatedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->publicId,
            'student_code' => $this->studentCode,
            'national_id' => $this->nationalId,
            'first_name' => $this->firstName,
            'middle_name' => $this->middleName,
            'last_name' => $this->lastName,
            'full_name' => $this->fullName,
            'gender' => $this->gender,
            'birth_date' => $this->birthDate,
            'birth_place' => $this->birthPlace,
            'nationality' => $this->nationality,
            'status' => $this->status,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
