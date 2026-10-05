<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Models\User;
use App\Support\Ops\OpsWorkspaceBootstrap;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

/**
 * Every workflow page (admission → transfers → students → enrollments → curriculum)
 * renders for an ops manager, including «المديريات والمدارس».
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
        $first->assertOk()->assertInertia(fn ($page) => $page
            ->component('admission/index')
            ->where('authorization.can_manage_schools', true)
            ->has('workspace.school_options', 1)
            ->has('workspace.school_options.0.branches'));

        // «المديريات والمدارس»: directorates → the user's schools → branches.
        $this->get('/organization/directorates-schools')->assertOk()->assertInertia(fn ($page) => $page
            ->component('organization/directorates-schools')
            ->where('authorization.can_manage_directorates', true)
            ->where('directorates', fn ($directorates) => collect($directorates)
                ->flatMap(fn ($directorate) => collect($directorate['schools'])->pluck('id'))
                ->contains($context['school_id'])));

        foreach (['/admission/submitted', '/transfers', '/students', '/enrollments', '/curriculum'] as $path) {
            $this->get($path)->assertOk();
        }
    }
}
