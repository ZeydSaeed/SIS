<?php

namespace App\Domain\Results\Events;

use App\Domain\Shared\DomainEvent;

final readonly class TranscriptIssued implements DomainEvent
{
    public function __construct(
        private int $transcriptId,
        private int $schoolId,
        private int $enrollmentId,
        private int $studentId,
        private int $academicYearId,
        private int $transcriptVersion,
        private string $transcriptNumber,
        private string $payloadHash,
        private \DateTimeImmutable $occurredAt,
    ) {}

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'transcript_id' => $this->transcriptId,
            'school_id' => $this->schoolId,
            'enrollment_id' => $this->enrollmentId,
            'student_id' => $this->studentId,
            'academic_year_id' => $this->academicYearId,
            'transcript_version' => $this->transcriptVersion,
            'transcript_number' => $this->transcriptNumber,
            'payload_hash' => $this->payloadHash,
            'occurred_at' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
