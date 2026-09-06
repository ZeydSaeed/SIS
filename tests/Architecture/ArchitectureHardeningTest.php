<?php

namespace Tests\Architecture;

use App\Architecture\ArchitectureBaseline;
use App\Architecture\ArchitectureStaticAnalyzer;
use App\Architecture\FeatureContractValidator;
use App\Architecture\SecurityFitnessChecker;
use Tests\TestCase;

class ArchitectureHardeningTest extends TestCase
{
    public function test_baseline_file_loads(): void
    {
        $baseline = new ArchitectureBaseline;

        $this->assertSame('1.0', $baseline->version());
        $this->assertNotEmpty($baseline->complexity());
        $this->assertNotEmpty($baseline->security());
    }

    public function test_static_analyzer_passes_on_clean_codebase(): void
    {
        $analyzer = new ArchitectureStaticAnalyzer;

        $this->assertTrue(
            $analyzer->passes(),
            implode("\n", $analyzer->validate()),
        );
    }

    public function test_security_fitness_passes(): void
    {
        $checker = new SecurityFitnessChecker;

        $this->assertSame([], $checker->validate());
    }

    public function test_enrollment_feature_contract_passes(): void
    {
        $contract = new FeatureContractValidator;

        $this->assertSame([], $contract->validate('Enrollment'));
    }
}
