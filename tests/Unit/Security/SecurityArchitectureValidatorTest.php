<?php

namespace Tests\Unit\Security;

use App\Security\Validation\SecurityArchitectureValidator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SecurityArchitectureValidatorTest extends TestCase
{
    #[Test]
    public function security_architecture_validator_passes_on_baseline_project(): void
    {
        $validator = app(SecurityArchitectureValidator::class);

        $this->assertTrue(
            $validator->passes(),
            implode("\n", $validator->validate()),
        );
    }
}
