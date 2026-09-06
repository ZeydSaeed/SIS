<?php

namespace Tests\Architecture;

use App\Architecture\ArchitectureValidator;
use App\Domain\Student\Specifications\ActiveStudentSpecification;
use App\Domain\Student\ValueObjects\StudentCode;
use Tests\TestCase;

class CleanArchitectureTest extends TestCase
{
    public function test_domain_layer_has_no_laravel_dependencies(): void
    {
        $validator = new ArchitectureValidator;

        $this->assertTrue(
            $validator->passes(),
            "Architecture violations:\n".implode("\n", $validator->validate())
        );
    }

    public function test_specification_pattern_composes(): void
    {
        $active = new ActiveStudentSpecification;
        $candidate = (object) ['status' => 1];

        $this->assertTrue($active->isSatisfiedBy($candidate));
        $this->assertTrue($active->and($active)->isSatisfiedBy($candidate));
    }

    public function test_student_code_value_object_rejects_empty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new StudentCode('');
    }
}
