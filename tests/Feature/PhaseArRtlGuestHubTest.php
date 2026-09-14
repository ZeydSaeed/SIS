<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PhaseArRtlGuestHubTest extends TestCase
{
    #[Test]
    public function guest_hub_html_is_arabic_rtl(): void
    {
        $response = $this->get('/hub?desktop=1');

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->component('hub')
            ->where('locale', 'ar')
            ->where('dir', 'rtl'));
        $this->assertStringContainsString('lang="ar"', $response->getContent());
        $this->assertStringContainsString('dir="rtl"', $response->getContent());
    }

    #[Test]
    public function guest_home_html_is_arabic_rtl(): void
    {
        $response = $this->get('/');

        $response->assertSuccessful();
        $this->assertStringContainsString('lang="ar"', $response->getContent());
        $this->assertStringContainsString('dir="rtl"', $response->getContent());
    }

    #[Test]
    public function guest_cannot_open_enrollments_without_login(): void
    {
        $this->get('/enrollments')->assertRedirect('/login');
    }
}
