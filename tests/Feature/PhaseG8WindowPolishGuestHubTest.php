<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PhaseG8WindowPolishGuestHubTest extends TestCase
{
    #[Test]
    public function guest_legacy_hub_redirects_to_login(): void
    {
        $this->get('/hub?desktop=1')->assertRedirect('/login');
    }

    #[Test]
    public function guest_home_redirects_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
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
