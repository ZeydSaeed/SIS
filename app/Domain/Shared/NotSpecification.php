<?php

namespace App\Domain\Shared;

final class NotSpecification extends AbstractSpecification
{
    public function __construct(
        private readonly Specification $inner,
    ) {}

    public function isSatisfiedBy(object $candidate): bool
    {
        return ! $this->inner->isSatisfiedBy($candidate);
    }
}
