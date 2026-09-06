<?php

namespace Tests\Feature\Intelligence;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecommendationUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_recommendations_page_requires_auth(): void
    {
        $this->get('/intelligence/recommendations')
            ->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_recommendations_page(): void
    {
        $this->withoutVite();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/intelligence/recommendations')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->component('intelligence/recommendations/index')
                ->has('stats')
                ->has('recommendations'));
    }
}
