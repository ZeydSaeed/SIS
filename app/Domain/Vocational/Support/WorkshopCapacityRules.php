<?php

namespace App\Domain\Vocational\Support;

final class WorkshopCapacityRules
{
    /**
     * @return list<string>
     */
    public static function validate(int $capacity, int $safetyCapacity): array
    {
        if ($capacity < 1) {
            return ['vocational.workshop_capacity_invalid'];
        }
        if ($safetyCapacity < 1) {
            return ['vocational.workshop_safety_capacity_invalid'];
        }
        if ($safetyCapacity > $capacity) {
            return ['vocational.workshop_safety_exceeds_capacity'];
        }

        return [];
    }

    public static function wouldExceedSafety(int $safetyCapacity, int $assignedStudents): bool
    {
        return $assignedStudents > $safetyCapacity;
    }
}
