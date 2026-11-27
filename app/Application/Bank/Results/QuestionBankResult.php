<?php

namespace App\Application\Bank\Results;

/** Common shape of the question bank command results (one subclass per command, ARCH-202). */
abstract readonly class QuestionBankResult
{
    public function __construct(
        public int $questionId,
        public ?int $versionId,
        public bool $fromIdempotencyCache = false,
    ) {}
}
