<?php

namespace App\Application\Results\Results;

final readonly class WithdrawResultPublicationResult
{
    public function __construct(
        public int $publicationId,
        public bool $fromIdempotencyCache = false,
    ) {}
}
