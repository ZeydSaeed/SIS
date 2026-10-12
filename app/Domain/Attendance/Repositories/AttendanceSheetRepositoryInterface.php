<?php

namespace App\Domain\Attendance\Repositories;

use App\Domain\Attendance\Data\AttendanceSheetSnapshot;
use App\Domain\Attendance\Data\LessonSlot;
use App\Domain\Attendance\Data\RosterEntry;
use App\Domain\Attendance\Data\SheetMark;
use App\Domain\Attendance\ValueObjects\SheetEventType;
use App\Domain\Attendance\ValueObjects\SheetStatus;

/** Write side of the attendance sheet lifecycle. Every mutation is version-guarded (optimistic + row lock). */
interface AttendanceSheetRepositoryInterface
{
    /** SELECT … FOR UPDATE on the session row (call inside the handler's transaction). */
    public function lockSheet(int $sessionId): ?AttendanceSheetSnapshot;

    /** The live (non-cancelled) session of a timetable lesson on a date, if one was opened already. */
    public function findLessonSession(int $schoolId, int $scheduleId, string $date): ?int;

    /**
     * Creates the lesson's session unless a live one exists (concurrent opens converge on one row — the partial
     * unique (school, schedule, date) is the authority).
     *
     * @return array{id: int, created: bool}
     */
    public function openLessonSession(LessonSlot $lesson, int $schoolId, int $academicYearId, ?int $policyId, int $origin, ?int $openedBy): array;

    /**
     * Bumps the version after an out-of-band change (official correction, excuse application), so an editor
     * holding the previous version cannot overwrite it.
     *
     * @return array{version: int, sheet_status: int}
     */
    public function touchVersion(int $sessionId): array;

    /**
     * Enrollments active in the section on the date (ATT-D4), narrowed to the group's members when the lesson is
     * for a group that has members.
     *
     * @return list<RosterEntry>
     */
    public function roster(int $schoolId, int $academicYearId, int $sectionId, ?int $groupId, string $date): array;

    /**
     * @return array<int, int> enrollment_id → stored status
     */
    public function statuses(int $sessionId, int $academicYearId): array;

    /**
     * Upserts the marks (one row per session · student; never deletes).
     *
     * @param  list<array{mark: SheetMark, student_id: int}>  $marks
     */
    public function saveMarks(AttendanceSheetSnapshot $sheet, array $marks, ?int $recordedBy, int $markedVia): int;

    /** version = version + 1 when it still equals $expectedVersion. */
    public function bumpVersion(int $sessionId, int $expectedVersion): bool;

    /**
     * Moves the sheet to $to (and bumps the version) when the version still matches.
     *
     * @param  array<string, mixed>  $stamps  submitted_* / reviewed_* / review_note / status / submission_count
     */
    public function transition(int $sessionId, int $expectedVersion, SheetStatus $to, array $stamps): bool;

    /**
     * @param  array<string, int>|null  $counts
     */
    public function appendEvent(int $schoolId, int $academicYearId, int $sessionId, SheetEventType $type, ?int $fromStatus, int $toStatus, int $version, ?int $actorUserId, ?string $note, ?array $counts): void;

    public function isHoliday(int $schoolId, int $academicYearId, string $date): bool;

    /**
     * Display data of an exceptional lesson when every part belongs to the school and year (section of the year's
     * class, period of the school, teacher working there, active subject); null otherwise.
     *
     * @return array<string, int|string|null>|null
     */
    public function exceptionalLesson(int $schoolId, int $academicYearId, int $sectionId, int $subjectId, int $teacherId, int $periodId): ?array;

    /**
     * Opens an exceptional (off-timetable) session; no-op returning created=false when an OPEN session already
     * exists for the natural key (section · subject · date · period).
     *
     * @param  array<string, int|string|null>  $snapshot
     * @return array{id: int, created: bool}
     */
    public function openExceptionalSession(int $schoolId, int $academicYearId, string $date, array $snapshot, ?int $policyId, ?int $openedBy): array;
}
