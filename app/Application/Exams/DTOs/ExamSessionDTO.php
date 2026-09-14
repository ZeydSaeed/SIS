<?php

namespace App\Application\Exams\DTOs;

final readonly class ExamSessionDTO
{
    public function __construct(
        public int $id,
        public int $examId,
        public int $schoolId,
        public int $subjectId,
        public string $sessionDate,
        public string $startTime,
        public string $endTime,
        public ?int $roomId,
        public int $maxGrade,
        public int $passGrade,
        public int $status,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'exam_id' => $this->examId,
            'school_id' => $this->schoolId,
            'subject_id' => $this->subjectId,
            'session_date' => $this->sessionDate,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'room_id' => $this->roomId,
            'max_grade' => $this->maxGrade,
            'pass_grade' => $this->passGrade,
            'status' => $this->status,
        ];
    }
}
