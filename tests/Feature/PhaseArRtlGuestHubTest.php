<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PhaseArRtlGuestHubTest extends TestCase
{
    #[Test]
    public function guest_legacy_hub_redirects_to_login(): void
    {
        $this->get('/hub?desktop=1')->assertRedirect('/login');
    }

    #[Test]
    public function login_html_is_arabic_rtl(): void
    {
        $response = $this->get('/login');

        $response->assertSuccessful();
        $this->assertStringContainsString('lang="ar"', $response->getContent());
        $this->assertStringContainsString('dir="rtl"', $response->getContent());
    }

    #[Test]
    public function guest_home_redirects_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    #[Test]
    public function guest_cannot_open_enrollments_without_login(): void
    {
        $this->get('/enrollments')->assertRedirect('/login');
    }
}
