<?php

namespace App\Application\Exams\DTOs;

final readonly class ExamEnrollmentDTO
{
    public function __construct(
        public int $id,
        public int $examSessionId,
        public int $schoolId,
        public int $enrollmentId,
        public int $status,
        public ?string $seatNumber,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'exam_session_id' => $this->examSessionId,
            'school_id' => $this->schoolId,
            'enrollment_id' => $this->enrollmentId,
            'status' => $this->status,
            'seat_number' => $this->seatNumber,
        ];
    }
}
