<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PhaseG8GuestHubTest extends TestCase
{
    #[Test]
    public function guest_home_redirects_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    #[Test]
    public function guest_hub_redirects_to_login(): void
    {
        $this->get('/hub')->assertRedirect('/login');
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
