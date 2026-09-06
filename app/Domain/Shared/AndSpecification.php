<?php

namespace App\Domain\Shared;

final class AndSpecification extends AbstractSpecification
{
    public function __construct(
        private readonly Specification $left,
        private readonly Specification $right,
    ) {}

    public function isSatisfiedBy(object $candidate): bool
    {
        return $this->left->isSatisfiedBy($candidate) && $this->right->isSatisfiedBy($candidate);
    }

    public function unsatisfiedReasons(object $candidate): array
    {
        return array_merge(
            $this->left->isSatisfiedBy($candidate) ? [] : $this->left->unsatisfiedReasons($candidate),
            $this->right->isSatisfiedBy($candidate) ? [] : $this->right->unsatisfiedReasons($candidate),
        );
    }
}
