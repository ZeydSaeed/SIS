<?php

namespace App\Domain\Timetable\Solver;

/**
 * One block to place: an occurrence of an activity, `length` consecutive lesson periods long, for its
 * targets (sections, optionally one group each), taught by its teachers, in one of its facilities.
 *
 * - facilities: candidate facility keys ('R{roomId}' room, 'W{workshopId}' workshop without a room);
 *   empty = no room needed. `rooms` maps each key to the room id stored on the lesson (null for 'W…').
 * - weeks: the cycle weeks the block occupies; weekNo = stored week (null = every week).
 * - initial: where the block sits on the current grid (optimize / repair), [day, start index, facility].
 * - blockedReason: set by the compiler when the block can never be placed (e.g. no room is big enough).
 */
final readonly class SolverCard
{
    /**
     * @param  list<int>  $teachers  teachers kept busy (lead first)
     * @param  list<array{0: int, 1: int|null}>  $targets  [section id, group id|null]
     * @param  list<string>  $facilities
     * @param  array<string, int|null>  $rooms
     * @param  list<int>  $weeks
     * @param  array{0: int, 1: int, 2: string|null}|null  $initial
     */
    public function __construct(
        public string $id,
        public string $activityKey,
        public ?int $activityId,
        public int $subjectId,
        public int $leadTeacherId,
        public ?int $coTeacherId,
        public array $teachers,
        public array $targets,
        public int $length,
        public array $facilities,
        public array $rooms,
        public array $weeks,
        public ?int $weekNo,
        public int $students,
        public ?array $initial = null,
        public ?string $blockedReason = null,
    ) {}

    /** @return list<int> */
    public function sectionIds(): array
    {
        return array_values(array_unique(array_map(static fn (array $t): int => $t[0], $this->targets)));
    }

    /** @param  array{0: int, 1: int, 2: string|null}|null  $initial */
    public function withInitial(?array $initial): self
    {
        return new self(
            $this->id, $this->activityKey, $this->activityId, $this->subjectId, $this->leadTeacherId, $this->coTeacherId,
            $this->teachers, $this->targets, $this->length, $this->facilities, $this->rooms, $this->weeks, $this->weekNo,
            $this->students, $initial, $this->blockedReason,
        );
    }
}
