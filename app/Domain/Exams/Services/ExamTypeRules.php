<?php

namespace App\Domain\Exams\Services;

use App\Domain\Exams\Data\ExamTypeSnapshot;
use App\Domain\Exams\Exceptions\ExamTypeException;

/**
 * An exam type's weight is the share of its exams in a term result (the weights used in a term must total 100).
 * Once an exam uses the type, its code and weight are frozen: changing them would silently change results.
 */
final class ExamTypeRules
{
    public static function code(string $code): string
    {
        $code = trim($code);
        if (preg_match('/^[A-Za-z0-9_-]{1,20}$/', $code) !== 1) {
            throw ExamTypeException::invalidCode();
        }

        return $code;
    }

    public static function name(string $name): string
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 100) {
            throw ExamTypeException::invalidName();
        }

        return $name;
    }

    public static function weight(int $weight): int
    {
        if ($weight < 0 || $weight > 100) {
            throw ExamTypeException::invalidWeight();
        }

        return $weight;
    }

    public static function assertEditable(ExamTypeSnapshot $type, string $code, int $weight): void
    {
        if ($type->inUse && ($type->code !== $code || $type->weightPercentage !== $weight)) {
            throw ExamTypeException::lockedInUse();
        }
    }
}
