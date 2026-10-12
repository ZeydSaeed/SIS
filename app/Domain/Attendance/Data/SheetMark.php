<?php

namespace App\Domain\Attendance\Data;

final readonly class SheetMark
{
    public function __construct(
        public int $enrollmentId,
        public int $status,
        public ?int $minutesLate = null,
        public ?string $note = null,
    ) {}

    /**
     * @param  array<string, mixed>  $row  enrollment_id, status, minutes_late?, note?
     */
    public static function fromArray(array $row): self
    {
        $note = isset($row['note']) && is_string($row['note']) ? trim($row['note']) : null;

        return new self(
            (int) $row['enrollment_id'],
            (int) $row['status'],
            isset($row['minutes_late']) && $row['minutes_late'] !== null && $row['minutes_late'] !== '' ? (int) $row['minutes_late'] : null,
            $note === '' ? null : $note,
        );
    }
}
