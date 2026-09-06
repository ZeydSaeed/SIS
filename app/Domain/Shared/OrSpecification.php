<?php

namespace App\Domain\Shared;

final class OrSpecification extends AbstractSpecification
{
    public function __construct(
        private readonly Specification $left,
        private readonly Specification $right,
    ) {}

    public function isSatisfiedBy(object $candidate): bool
    {
        return $this->left->isSatisfiedBy($candidate) || $this->right->isSatisfiedBy($candidate);
    }
}
