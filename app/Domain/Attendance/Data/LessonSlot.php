<?php

namespace App\Domain\Attendance\Data;

/**
 * One lesson of the effective timetable on a date (published version or working grid, with that date's
 * substitution applied). Frozen into attendance.sessions.lesson_snapshot when a sheet is opened.
 */
final readonly class LessonSlot
{
    public function __construct(
        public int $scheduleId,
        public string $date,
        public int $sectionId,
        public int $subjectId,
        public int $teacherId,
        public ?int $coTeacherId,
        public ?int $substituteTeacherId,
        public int $periodId,
        public int $periodNumber,
        public string $startTime,
        public string $endTime,
        public ?int $roomId,
        public ?int $groupId,
        public int $lessonType,
        public string $subjectName,
        public string $sectionName,
        public string $className,
        public ?string $roomName,
        public ?string $groupName,
        public string $teacherName,
    ) {}

    /** The teacher who actually teaches that day (the substitute when one is assigned). */
    public function effectiveTeacherId(): int
    {
        return $this->substituteTeacherId ?? $this->teacherId;
    }

    public function isTaughtBy(int $teacherId): bool
    {
        return $teacherId === $this->effectiveTeacherId() || $teacherId === $this->coTeacherId;
    }

    /**
     * @return array<string, int|string|null>
     */
    public function toArray(): array
    {
        return [
            'schedule_id' => $this->scheduleId,
            'date' => $this->date,
            'section_id' => $this->sectionId,
            'subject_id' => $this->subjectId,
            'teacher_id' => $this->teacherId,
            'co_teacher_id' => $this->coTeacherId,
            'substitute_teacher_id' => $this->substituteTeacherId,
            'effective_teacher_id' => $this->effectiveTeacherId(),
            'period_id' => $this->periodId,
            'period_number' => $this->periodNumber,
            'start_time' => substr($this->startTime, 0, 5),
            'end_time' => substr($this->endTime, 0, 5),
            'room_id' => $this->roomId,
            'group_id' => $this->groupId,
            'lesson_type' => $this->lessonType,
            'subject_name' => $this->subjectName,
            'section_name' => $this->sectionName,
            'class_name' => $this->className,
            'room_name' => $this->roomName,
            'group_name' => $this->groupName,
            'teacher_name' => $this->teacherName,
        ];
    }
}
