<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Models\User;
use App\Support\Ops\OpsWorkspaceBootstrap;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

/**
 * Every workflow page (admission → transfers → students → enrollments → curriculum)
 * renders for an ops manager, including the on-demand organization registries.
 */
final class OpsWorkflowPagesSmokePostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    #[Test]
    public function workflow_pages_render_for_an_ops_manager(): void
    {
        $user = User::factory()->create();
        config(['security.ops_bootstrap_enabled' => true]);
        $context = app(OpsWorkspaceBootstrap::class)->bootstrapFor($user);

        $this->actingAs($user);
        $this->withSession([
            'current_school_id' => $context['school_id'],
            'current_academic_year_id' => $context['academic_year_id'],
        ]);

        $first = $this->get('/admission');
        $version = (string) ($first->viewData('page')['version'] ?? '');
        $first->assertOk()->assertInertia(fn ($page) => $page
            ->component('admission/index')
            ->where('authorization.can_manage_schools', true)
            ->where('authorization.can_manage_directorates', true)
            ->has('workspace.school_options', 1)
            ->has('workspace.school_options.0.branches'));

        // Optional props load only on a partial reload (the registry sheets).
        $this->get('/admission', [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
            'X-Inertia-Partial-Component' => 'admission/index',
            'X-Inertia-Partial-Data' => 'schoolRegistry,directorateRegistry',
        ])->assertOk()->assertJsonPath('props.schoolRegistry.schools.0.id', $context['school_id'])
            ->assertJsonStructure(['props' => ['directorateRegistry' => ['directorates', 'schools']]]);

        foreach (['/admission/submitted', '/transfers', '/students', '/enrollments', '/curriculum'] as $path) {
            $this->get($path)->assertOk();
        }
    }
}
