<?php

namespace App\Domain\Results\Data;

final readonly class CurrentTermResultSnapshot
{
    public function __construct(
        public int $id,
        public int $resultVersion,
        public string $sourceFingerprint,
        public ?string $weightedTotal,
        public bool $incomplete,
    ) {}
}
