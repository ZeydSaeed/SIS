<?php

namespace App\Domain\Shared;

interface Specification
{
    public function isSatisfiedBy(object $candidate): bool;

    /**
     * @return list<string>
     */
    public function unsatisfiedReasons(object $candidate): array;
}
