<?php

namespace App\Domain\Shared;

abstract class AbstractSpecification implements Specification
{
    public function and(Specification $other): Specification
    {
        return new AndSpecification($this, $other);
    }

    public function or(Specification $other): Specification
    {
        return new OrSpecification($this, $other);
    }

    public function not(): Specification
    {
        return new NotSpecification($this);
    }

    public function unsatisfiedReasons(object $candidate): array
    {
        return $this->isSatisfiedBy($candidate) ? [] : ['Specification not satisfied'];
    }
}
