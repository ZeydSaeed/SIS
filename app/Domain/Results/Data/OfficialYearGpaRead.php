<?php

namespace App\Domain\Results\Data;

final readonly class OfficialYearGpaRead
{
    public function __construct(
        public int $id,
        public int $resultVersion,
        public string $sourceFingerprint,
        public ?string $gpaValue,
        public string $scaleCode,
        public bool $incomplete,
    ) {}
}
