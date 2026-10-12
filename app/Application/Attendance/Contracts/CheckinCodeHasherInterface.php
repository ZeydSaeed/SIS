<?php

namespace App\Application\Attendance\Contracts;

/** Keyed hashing of staff check-in codes — the plain code is shown once and never stored. */
interface CheckinCodeHasherInterface
{
    public function hash(int $schoolId, string $code): string;

    public function generate(): string;
}
