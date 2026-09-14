<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PhaseG8GuestHubTest extends TestCase
{
    #[Test]
    public function guest_can_open_home_hub(): void
    {
        $this->get('/')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page->component('hub'));
    }

    #[Test]
    public function guest_can_open_hub_route(): void
    {
        $this->get('/hub')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page->component('hub'));
    }

    #[Test]
    public function guest_is_redirected_from_protected_students(): void
    {
        $this->get('/students')->assertRedirect('/login');
    }

    #[Test]
    public function guest_is_redirected_from_protected_enrollments(): void
    {
        $this->get('/enrollments')->assertRedirect('/login');
    }

    #[Test]
    public function guest_is_redirected_from_protected_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }
}
