<?php

namespace App\Domain\Curriculum\ValueObjects;

/**
 * «نوع المادة» of a subject: one or several types, each with its weekly hours (عملي 2 + نظري 3 …).
 * The first type (ascending) is the primary one (curriculum.subjects.subject_type); when any hours are given,
 * the subject's hours (credit_hours) are their total.
 */
final readonly class SubjectTypeSet
{
    /** @param  array<int, ?int>  $hours  type → hours, ascending by type */
    private function __construct(
        public array $hours,
    ) {}

    /**
     * @param  array<mixed>  $types
     * @param  array<mixed>  $hours  type → hours (null / '' = not given); entries for unchosen types are ignored
     */
    public static function from(array $types, array $hours = []): ?self
    {
        $set = SubjectType::normalizeSet($types);
        if ($set === null) {
            return null;
        }
        $byType = [];
        foreach ($set as $type) {
            $value = $hours[$type] ?? $hours[(string) $type] ?? null;
            if ($value === null || $value === '') {
                $byType[$type] = null;

                continue;
            }
            if (! is_numeric($value) || (int) $value < 0 || (int) $value > 40) {
                return null;
            }
            $byType[$type] = (int) $value;
        }

        return new self($byType);
    }

    public function primary(): int
    {
        return (int) array_key_first($this->hours);
    }

    /** @return list<int> */
    public function types(): array
    {
        return array_keys($this->hours);
    }

    /** Total weekly hours, or null when no type carries hours. */
    public function totalHours(): ?int
    {
        $given = array_filter($this->hours, static fn (?int $h): bool => $h !== null);

        return $given === [] ? null : array_sum($given);
    }
}
