<?php

namespace Tests\Architecture;

use App\Architecture\ArchitectureDependencyGraph;
use App\Architecture\ArchitectureFitnessReport;
use App\Architecture\ArchitectureValidator;
use Tests\TestCase;

class ArchitectureFitnessTest extends TestCase
{
    public function test_all_fitness_categories_pass(): void
    {
        $fitness = new ArchitectureFitnessReport(
            new ArchitectureValidator,
            new ArchitectureDependencyGraph,
        );

        $categories = $fitness->categories();

        $this->assertArrayHasKey('domain_purity', $categories);
        $this->assertArrayHasKey('dependency_direction', $categories);
        $this->assertArrayHasKey('application_isolation', $categories);
        $this->assertArrayHasKey('controller_thinness', $categories);
        $this->assertArrayHasKey('handler_rules', $categories);
        $this->assertArrayHasKey('intelligence_alignment', $categories);

        foreach ($categories as $name => $category) {
            $this->assertSame(
                'PASS',
                $category['status'],
                "Fitness category {$name} failed: ".($fitness->allViolations()[0] ?? 'unknown'),
            );
        }
    }

    public function test_dependency_graph_has_allowed_diagram(): void
    {
        $graph = new ArchitectureDependencyGraph;

        $this->assertStringContainsString('Presentation', $graph->diagram());
        $this->assertStringContainsString('Application', $graph->diagram());
        $this->assertStringContainsString('Domain', $graph->diagram());
        $this->assertStringContainsString('Infrastructure', $graph->diagram());
    }
}
