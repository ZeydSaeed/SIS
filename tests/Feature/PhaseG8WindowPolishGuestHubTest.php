<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PhaseG8WindowPolishGuestHubTest extends TestCase
{
    #[Test]
    public function guest_can_open_hub_with_desktop_query(): void
    {
        $this->get('/hub?desktop=1')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page->component('hub'));
    }

    #[Test]
    public function guest_home_still_serves_hub_without_login(): void
    {
        $this->get('/')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page->component('hub'));
    }

    #[Test]
    public function guest_cannot_open_attendance_without_login(): void
    {
        $this->get('/attendance')->assertRedirect('/login');
    }

    #[Test]
    public function guest_cannot_open_grades_without_login(): void
    {
        $this->get('/grades')->assertRedirect('/login');
    }

    #[Test]
    public function guest_cannot_open_authenticated_dashboard_without_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }
}
