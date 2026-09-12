<?php

namespace App\Domain\Results\Data;

final readonly class CurrentGpaResultSnapshot
{
    public function __construct(
        public int $id,
        public int $resultVersion,
        public string $sourceFingerprint,
        public ?string $gpaValue,
        public bool $incomplete,
    ) {}
}
